<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

class PauseForParts
{
    use EnsuresAssignedTechnician;

    public function __construct(private TransitionServiceRequest $transition) {}

    public function handle(ServiceRequest $serviceRequest, User $technician, string $waitingFor): ServiceRequest
    {
        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::WaitingParts,
            $technician,
            "بانتظار: {$waitingFor}",
            fn (ServiceRequest $locked) => $this->ensureAssignedTo($locked, $technician),
            'paused',
        );
    }
}
