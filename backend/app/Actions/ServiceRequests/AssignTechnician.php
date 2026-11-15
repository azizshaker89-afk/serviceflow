<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Assigns or reassigns a technician. Reassignment closes the previous assignment row,
 * so the assignments table is the full history of who held the request and when.
 */
class AssignTechnician
{
    public function __construct(private TransitionServiceRequest $transition) {}

    public function handle(ServiceRequest $serviceRequest, User $technician, User $actor, ?string $note = null): ServiceRequest
    {
        if (! $technician->is_active || ! $technician->isTechnician()) {
            throw new BusinessRuleException('المستخدم المختار ليس فنيًا نشطًا.');
        }

        $previousName = null;

        return $this->transition->handle(
            $serviceRequest,
            ServiceRequestStatus::Assigned,
            $actor,
            function () use ($technician, $note, &$previousName): string {
                $line = $previousName
                    ? "إعادة تعيين من {$previousName} إلى {$technician->name}"
                    : "تعيين: {$technician->name}";

                return $note ? "{$line}. {$note}" : $line;
            },
            function (ServiceRequest $locked) use ($technician, $actor, $note, &$previousName): void {
                if ($locked->assigned_technician_id === $technician->id) {
                    throw new BusinessRuleException("الطلب معيّن بالفعل للفني {$technician->name}.");
                }

                if ($locked->assigned_technician_id !== null) {
                    $previousName = $locked->technician?->name;
                    $locked->assignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
                }

                $locked->assigned_technician_id = $technician->id;
                $locked->assigned_at = now();

                $locked->assignments()->create([
                    'technician_id' => $technician->id,
                    'assigned_by' => $actor->id,
                    'note' => $note,
                    'assigned_at' => now(),
                ]);
            },
            'assigned',
        );
    }
}
