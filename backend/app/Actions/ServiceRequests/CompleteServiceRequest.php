<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * A request can't be marked done until the work that justifies the invoice is on record:
 * at least one work log, and either the parts used or an explicit "no parts needed".
 */
class CompleteServiceRequest
{
    use EnsuresAssignedTechnician;

    public function __construct(private TransitionServiceRequest $transition) {}

    public function handle(ServiceRequest $serviceRequest, User $technician, bool $noPartsConfirmed = false, ?string $note = null): ServiceRequest
    {
        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::Completed,
            $technician,
            $note ?: 'تمت الصيانة',
            function (ServiceRequest $locked) use ($technician, $noPartsConfirmed): void {
                $this->ensureAssignedTo($locked, $technician);

                if ($locked->workLogs()->doesntExist()) {
                    throw new BusinessRuleException('سجّل ساعات العمل قبل إنهاء الصيانة.');
                }

                $hasParts = $locked->parts()->exists();

                if (! $hasParts && ! $noPartsConfirmed) {
                    throw new BusinessRuleException('سجّل قطع الغيار المستخدمة، أو أكّد أن الصيانة لم تحتج أي قطع.');
                }

                $locked->no_parts_confirmed = ! $hasParts;
                $locked->completed_at = now();
            },
            'completed',
        );
    }
}
