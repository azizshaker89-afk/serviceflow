<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

class ResumeWork
{
    use EnsuresAssignedTechnician;

    public function __construct(private TransitionServiceRequest $transition) {}

    public function handle(ServiceRequest $serviceRequest, User $technician): ServiceRequest
    {
        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::InProgress,
            $technician,
            'وصلت القطع واستُكمل العمل',
            fn (ServiceRequest $locked) => $this->ensureAssignedTo($locked, $technician),
            'resumed',
        );
    }
}
