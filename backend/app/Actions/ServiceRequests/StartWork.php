<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * The technician accepts the job and starts it (accepting and starting are one step by design).
 */
class StartWork
{
    use EnsuresAssignedTechnician;

    public function __construct(private TransitionServiceRequest $transition) {}

    public function handle(ServiceRequest $serviceRequest, User $technician): ServiceRequest
    {
        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::InProgress,
            $technician,
            'استلم الفني المهمة وبدأ العمل',
            function (ServiceRequest $locked) use ($technician): void {
                $this->ensureAssignedTo($locked, $technician);
                $locked->started_at ??= now();
            },
            'started',
        );
    }
}
