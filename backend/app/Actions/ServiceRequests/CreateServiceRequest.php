<?php

namespace App\Actions\ServiceRequests;

use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for creating a request, used by both the reception desk and the public form,
 * so every request starts with the same defaults and its first history row.
 */
class CreateServiceRequest
{
    /**
     * @param  array{service_category_id: int, description: string, priority?: Priority|string|null, address?: ?string, preferred_date?: ?string}  $attributes
     */
    public function handle(Customer $customer, array $attributes, ?User $actor, RequestSource $source): ServiceRequest
    {
        return DB::transaction(function () use ($customer, $attributes, $actor, $source): ServiceRequest {
            $serviceRequest = $customer->serviceRequests()->create([
                'service_category_id' => $attributes['service_category_id'],
                'description' => $attributes['description'],
                'priority' => $attributes['priority'] ?? Priority::Normal,
                'address' => $attributes['address'] ?? $customer->address,
                'preferred_date' => $attributes['preferred_date'] ?? null,
                'created_by' => $actor?->id,
                'source' => $source,
            ]);

            $serviceRequest->statusHistories()->create([
                'from_status' => null,
                'to_status' => ServiceRequestStatus::New,
                'changed_by' => $actor?->id,
                'note' => $source->label(),
            ]);

            return $serviceRequest;
        });
    }
}
