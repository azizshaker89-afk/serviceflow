<?php

namespace App\Actions\Inventory;

use App\Actions\ServiceRequests\EnsuresAssignedTechnician;
use App\Enums\ServiceRequestStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\SparePart;
use App\Models\User;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * The technician issues a part from stock to a request.
 *
 * Price and cost are copied from the part at this moment (snapshot); the technician never sends a price.
 * Stock is taken through the ledger in the same transaction as the request line, so a request can
 * never show a part that stock did not give out, and an insufficient balance rolls back both.
 */
class AddPartToRequest
{
    use EnsuresAssignedTechnician;

    public function __construct(private StockLedger $stock) {}

    public function handle(ServiceRequest $serviceRequest, SparePart $part, int $quantity, User $technician): ServiceRequestPart
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('الكمية يجب أن تكون 1 على الأقل.');
        }

        return DB::transaction(function () use ($serviceRequest, $part, $quantity, $technician): ServiceRequestPart {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if (! in_array($locked->status, [ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingParts], true)) {
                throw new BusinessRuleException('صرف القطع متاح فقط أثناء تنفيذ الطلب.');
            }

            $this->ensureAssignedTo($locked, $technician);

            if (! $part->is_active) {
                throw new BusinessRuleException("القطعة «{$part->name}» موقوفة ولا يمكن صرفها.");
            }

            $this->stock->move($part, -$quantity, StockMovementType::Usage, $technician, $locked, "صرف للطلب {$locked->code}");

            /** @var ServiceRequestPart|null $line */
            $line = $locked->parts()->where('spare_part_id', $part->id)->first();

            if ($line !== null) {
                $line->increment('quantity', $quantity);
            } else {
                $line = $locked->parts()->create([
                    'spare_part_id' => $part->id,
                    'quantity' => $quantity,
                    'unit_cost' => $part->unit_cost,
                    'unit_price' => $part->unit_price,
                    'added_by' => $technician->id,
                ]);
            }

            if ($locked->no_parts_confirmed) {
                $locked->forceFill(['no_parts_confirmed' => false])->save();
            }

            activity('inventory')
                ->performedOn($locked)
                ->causedBy($technician)
                ->event('part_issued')
                ->withProperties(['spare_part_id' => $part->id, 'quantity' => $quantity, 'unit_price' => $part->unit_price])
                ->log("{$locked->code}: صرف {$quantity} × {$part->name}");

            return $line->refresh();
        });
    }
}
