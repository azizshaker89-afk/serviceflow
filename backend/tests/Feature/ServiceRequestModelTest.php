<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_request_gets_a_readable_code_and_starts_as_new(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->assertSame(sprintf('SR-%s-%05d', now()->format('Y'), $request->id), $request->fresh()->code);
        $this->assertSame(ServiceRequestStatus::New, $request->fresh()->status);
    }

    public function test_due_at_follows_the_priority_sla(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');

        $urgent = ServiceRequest::factory()->create(['priority' => Priority::Urgent]);
        $low = ServiceRequest::factory()->create(['priority' => Priority::Low]);

        $this->assertEquals(Carbon::parse('2026-11-15 14:00:00'), $urgent->due_at);
        $this->assertEquals(Carbon::parse('2026-11-18 10:00:00'), $low->due_at);
    }

    public function test_status_cannot_be_mass_assigned(): void
    {
        $request = ServiceRequest::factory()->create();

        $request->update(['status' => ServiceRequestStatus::Completed->value]);

        $this->assertSame(ServiceRequestStatus::New, $request->fresh()->status);
    }

    public function test_overdue_scope_only_counts_open_requests_past_their_due_date(): void
    {
        $overdueOpen = ServiceRequest::factory()->overdue()->create();
        ServiceRequest::factory()->create();
        ServiceRequest::factory()->overdue()->inStatus(ServiceRequestStatus::Completed)->create();
        ServiceRequest::factory()->overdue()->inStatus(ServiceRequestStatus::Cancelled)->create();

        $this->assertEquals([$overdueOpen->id], ServiceRequest::overdue()->pluck('id')->all());
        $this->assertTrue($overdueOpen->is_overdue);
    }

    public function test_assigned_to_scope_returns_only_that_technicians_requests(): void
    {
        $technician = User::factory()->create();
        $mine = ServiceRequest::factory()->assignedTo($technician)->create();
        ServiceRequest::factory()->assignedTo(User::factory()->create())->create();

        $this->assertEquals([$mine->id], ServiceRequest::assignedTo($technician)->pluck('id')->all());
    }
}
