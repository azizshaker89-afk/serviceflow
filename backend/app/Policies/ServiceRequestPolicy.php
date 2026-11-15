<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every rule here is enforced by the API. The frontend only reads the result
 * through the resource's allowed_actions to decide which buttons to show.
 */
class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAllServiceRequests->value)
            || $user->can(Permission::WorkOnServiceRequests->value);
    }

    /**
     * A technician asking for someone else's request gets 404, not 403,
     * so the API does not even confirm that the request exists.
     */
    public function view(User $user, ServiceRequest $serviceRequest): Response
    {
        if ($user->can(Permission::ViewAllServiceRequests->value)) {
            return Response::allow();
        }

        return $this->isAssignedTechnician($user, $serviceRequest)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateServiceRequests->value);
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can(Permission::UpdateServiceRequests->value)
            && in_array($serviceRequest->status, [ServiceRequestStatus::New, ServiceRequestStatus::Assigned], true);
    }

    public function assign(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can(Permission::AssignServiceRequests->value)
            && $serviceRequest->status->canTransitionTo(ServiceRequestStatus::Assigned);
    }

    public function start(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->canWorkOn($user, $serviceRequest)
            && $serviceRequest->status === ServiceRequestStatus::Assigned;
    }

    public function pause(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->canWorkOn($user, $serviceRequest)
            && $serviceRequest->status === ServiceRequestStatus::InProgress;
    }

    public function resume(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->canWorkOn($user, $serviceRequest)
            && $serviceRequest->status === ServiceRequestStatus::WaitingParts;
    }

    public function manageParts(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->canWorkOn($user, $serviceRequest)
            && in_array($serviceRequest->status, [ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingParts], true);
    }

    public function logWork(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->pause($user, $serviceRequest);
    }

    public function complete(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->pause($user, $serviceRequest);
    }

    /**
     * Receptionists may only cancel requests nobody has started on yet.
     */
    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        if (! $user->can(Permission::CancelServiceRequests->value)
            || ! $serviceRequest->status->canTransitionTo(ServiceRequestStatus::Cancelled)) {
            return false;
        }

        return $user->can(Permission::AssignServiceRequests->value)
            || $serviceRequest->status === ServiceRequestStatus::New;
    }

    public function issueInvoice(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can(Permission::ManageInvoices->value)
            && $serviceRequest->status === ServiceRequestStatus::Completed
            && ! $serviceRequest->invoice()->exists();
    }

    public function delete(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can(Permission::AssignServiceRequests->value)
            && $serviceRequest->status === ServiceRequestStatus::New;
    }

    private function canWorkOn(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->can(Permission::WorkOnServiceRequests->value)
            && $this->isAssignedTechnician($user, $serviceRequest);
    }

    private function isAssignedTechnician(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->assigned_technician_id !== null
            && $serviceRequest->assigned_technician_id === $user->id;
    }
}
