<?php

namespace Tests\Unit;

use App\Enums\ServiceRequestStatus as Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServiceRequestStatusTest extends TestCase
{
    /**
     * @return array<string, array{Status, Status}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'new → assigned' => [Status::New, Status::Assigned],
            'assigned → reassigned' => [Status::Assigned, Status::Assigned],
            'assigned → in progress' => [Status::Assigned, Status::InProgress],
            'in progress → waiting parts' => [Status::InProgress, Status::WaitingParts],
            'waiting parts → in progress' => [Status::WaitingParts, Status::InProgress],
            'in progress → completed' => [Status::InProgress, Status::Completed],
            'in progress → cancelled' => [Status::InProgress, Status::Cancelled],
        ];
    }

    /**
     * @return array<string, array{Status, Status}>
     */
    public static function forbiddenTransitions(): array
    {
        return [
            'new → completed (skips the work)' => [Status::New, Status::Completed],
            'new → in progress (no technician)' => [Status::New, Status::InProgress],
            'completed → in progress (reopen)' => [Status::Completed, Status::InProgress],
            'cancelled → assigned (revive)' => [Status::Cancelled, Status::Assigned],
            'waiting parts → completed (parts not arrived)' => [Status::WaitingParts, Status::Completed],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_are_permitted(Status $from, Status $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_illogical_transitions_are_rejected(Status $from, Status $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_only_completed_and_cancelled_are_final(): void
    {
        $finals = array_filter(Status::cases(), fn (Status $status) => $status->isFinal());

        $this->assertEqualsCanonicalizing([Status::Completed, Status::Cancelled], array_values($finals));
    }

    public function test_every_status_has_an_arabic_label(): void
    {
        foreach (Status::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }
}
