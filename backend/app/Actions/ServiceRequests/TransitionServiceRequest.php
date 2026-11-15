<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The one place a request's status changes.
 *
 * 1. Locks the request row, so two people acting on the same request are serialized.
 * 2. Re-checks the transition against the locked (current) status, not the stale copy the
 *    caller loaded: the policy may have said yes a moment before someone else moved it.
 * 3. Runs the caller's guard/mutation, sets the status, writes the history row and the
 *    activity log, all in one transaction. Any exception rolls everything back.
 */
class TransitionServiceRequest
{
    /**
     * @param  string|(Closure(ServiceRequest): ?string)|null  $note  A closure is evaluated after $apply, under the lock.
     * @param  (Closure(ServiceRequest): void)|null  $apply  Extra guards and column changes, run under the lock.
     */
    public function handle(
        ServiceRequest $serviceRequest,
        ServiceRequestStatus $to,
        ?User $actor,
        string|Closure|null $note = null,
        ?Closure $apply = null,
        string $event = 'status_changed',
    ): ServiceRequest {
        return DB::transaction(function () use ($serviceRequest, $to, $actor, $note, $apply, $event): ServiceRequest {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new BusinessRuleException("لا يمكن نقل الطلب من «{$from->label()}» إلى «{$to->label()}».");
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $note = $note instanceof Closure ? $note($locked) : $note;

            $locked->status = $to;
            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            activity('service-requests')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event($event)
                ->withProperties(['from' => $from->value, 'to' => $to->value, 'note' => $note])
                ->log("{$locked->code}: {$from->label()} ← {$to->label()}");

            $serviceRequest->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }
}
