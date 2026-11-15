<?php

namespace App\Actions\Inventory;

use App\Actions\ServiceRequests\EnsuresAssignedTechnician;
use App\Enums\ServiceRequestStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\User;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Returns a whole part line to stock (issued by mistake, or not used after all).
 * Only while the request is still open: once completed, the parts are part of the invoice.
 */
class RemovePartFromRequest
{
    use EnsuresAssignedTechnician;

    public function __construct(private StockLedger $stock) {}

    public function handle(ServiceRequest $serviceRequest, ServiceRequestPart $line, User $technician): void
    {
        DB::transaction(function () use ($serviceRequest, $line, $technician): void {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if (! in_array($locked->status, [ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingParts], true)) {
                throw new BusinessRuleException('لا يمكن إرجاع القطع بعد إنهاء الطلب أو إلغائه.');
            }

            $this->ensureAssignedTo($locked, $technician);

            $line = $locked->parts()->with('sparePart')->lockForUpdate()->findOrFail($line->id);

            $this->stock->move($line->sparePart, $line->quantity, StockMovementType::Return, $technician, $locked, "إرجاع من الطلب {$locked->code}");
            $line->delete();

            activity('inventory')
                ->performedOn($locked)
                ->causedBy($technician)
                ->event('part_returned')
                ->withProperties(['spare_part_id' => $line->spare_part_id, 'quantity' => $line->quantity])
                ->log("{$locked->code}: إرجاع {$line->quantity} × {$line->sparePart->name} للمخزن");
        });
    }
}
