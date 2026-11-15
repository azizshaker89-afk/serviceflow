<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Support\Facades\DB;

/**
 * Hours are logged with a snapshot of the technician's rate, so a later raise
 * never changes the labor cost of work that was already done.
 */
class LogWork
{
    use EnsuresAssignedTechnician;

    public function handle(ServiceRequest $serviceRequest, User $technician, string $hours, ?string $note = null): WorkLog
    {
        return DB::transaction(function () use ($serviceRequest, $technician, $hours, $note): WorkLog {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if ($locked->status !== ServiceRequestStatus::InProgress) {
                throw new BusinessRuleException('تسجيل ساعات العمل متاح فقط أثناء تنفيذ الطلب.');
            }

            $this->ensureAssignedTo($locked, $technician);

            $rate = $technician->technicianProfile?->hourly_rate;

            if ($rate === null) {
                throw new BusinessRuleException('لا يوجد سعر ساعة مسجل لهذا الفني. راجع مدير النظام.');
            }

            $log = $locked->workLogs()->create([
                'technician_id' => $technician->id,
                'hours' => $hours,
                'hourly_rate' => $rate,
                'note' => $note,
                'logged_at' => now(),
            ]);

            activity('service-requests')
                ->performedOn($locked)
                ->causedBy($technician)
                ->event('work_logged')
                ->withProperties(['hours' => $hours, 'hourly_rate' => $rate])
                ->log("{$locked->code}: تسجيل {$hours} ساعة عمل");

            return $log;
        });
    }
}
