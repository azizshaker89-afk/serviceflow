<?php

namespace App\Actions\ServiceRequests;

use App\Enums\Priority;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the request details (not its status). Changing the priority moves the SLA deadline,
 * always measured from when the request was received.
 */
class UpdateServiceRequest
{
    /**
     * @param  array{service_category_id?: int, priority?: string, description?: string, address?: ?string, preferred_date?: ?string}  $attributes
     */
    public function handle(ServiceRequest $serviceRequest, array $attributes, User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $attributes, $actor): ServiceRequest {
            $serviceRequest->fill($attributes);

            if ($serviceRequest->isDirty('priority')) {
                $serviceRequest->due_at = $serviceRequest->created_at->copy()
                    ->addHours(Priority::from($serviceRequest->priority->value)->slaHours());
            }

            $changes = $serviceRequest->getDirty();
            $serviceRequest->save();

            if ($changes !== []) {
                activity('service-requests')
                    ->performedOn($serviceRequest)
                    ->causedBy($actor)
                    ->event('updated')
                    ->withProperties(['changes' => array_keys($changes)])
                    ->log("{$serviceRequest->code}: تعديل بيانات الطلب");
            }

            return $serviceRequest;
        });
    }
}
