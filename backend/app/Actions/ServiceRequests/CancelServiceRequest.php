<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Enums\StockMovementType;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Inventory\StockLedger;

/**
 * Cancelling after parts were issued returns every unit to stock in the same transaction
 * as the status change. The request's part lines are removed; the stock ledger keeps
 * both the original usage and the return, so nothing disappears from the audit trail.
 */
class CancelServiceRequest
{
    public function __construct(
        private TransitionServiceRequest $transition,
        private StockLedger $stock,
    ) {}

    public function handle(ServiceRequest $serviceRequest, User $actor, string $reason): ServiceRequest
    {
        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::Cancelled,
            $actor,
            $reason,
            function (ServiceRequest $locked) use ($actor, $reason): void {
                $locked->parts()->with('sparePart')->orderBy('spare_part_id')->get()
                    ->each(function ($line) use ($locked, $actor): void {
                        $this->stock->move(
                            $line->sparePart,
                            $line->quantity,
                            StockMovementType::Return,
                            $actor,
                            $locked,
                            "إلغاء الطلب {$locked->code}",
                        );
                        $line->delete();
                    });

                $locked->cancelled_at = now();
                $locked->cancellation_reason = $reason;
            },
            'cancelled',
        );
    }
}
