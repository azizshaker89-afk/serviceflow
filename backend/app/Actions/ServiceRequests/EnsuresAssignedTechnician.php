<?php

namespace App\Actions\ServiceRequests;

use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Re-checked under the row lock: the request may have been reassigned
 * between the policy check and this transaction.
 */
trait EnsuresAssignedTechnician
{
    private function ensureAssignedTo(ServiceRequest $locked, User $technician): void
    {
        if ($locked->assigned_technician_id !== $technician->id) {
            throw new BusinessRuleException('هذا الطلب لم يعد معيّنًا لك. ربما أعاد المدير تعيينه لفني آخر.');
        }
    }
}
