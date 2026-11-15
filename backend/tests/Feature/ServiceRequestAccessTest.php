<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    public function test_a_technician_lists_only_their_own_requests(): void
    {
        $technician = $this->userWithRole(RoleName::Technician);
        $mine = ServiceRequest::factory()->assignedTo($technician)->create();
        ServiceRequest::factory()->assignedTo($this->userWithRole(RoleName::Technician))->create();
        ServiceRequest::factory()->create();

        $this->actingAs($technician)->getJson('/api/service-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $mine->id);
    }

    public function test_a_technician_gets_404_for_another_technicians_request(): void
    {
        $other = ServiceRequest::factory()->assignedTo($this->userWithRole(RoleName::Technician))->create();

        $this->actingAs($this->userWithRole(RoleName::Technician))
            ->getJson("/api/service-requests/{$other->id}")
            ->assertNotFound();
    }

    public function test_reception_sees_every_request(): void
    {
        ServiceRequest::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::Receptionist))->getJson('/api/service-requests')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_allowed_actions_follow_role_and_status(): void
    {
        $technician = $this->userWithRole(RoleName::Technician);
        $request = ServiceRequest::factory()->assignedTo($technician)->create();

        $this->actingAs($technician)->getJson("/api/service-requests/{$request->id}")
            ->assertJsonPath('data.allowed_actions', ['start']);

        $this->actingAs($this->userWithRole(RoleName::Admin))->getJson("/api/service-requests/{$request->id}")
            ->assertJsonPath('data.allowed_actions', ['update', 'assign', 'cancel']);

        $this->actingAs($this->userWithRole(RoleName::Accountant))->getJson("/api/service-requests/{$request->id}")
            ->assertJsonPath('data.allowed_actions', []);
    }

    public function test_reception_may_cancel_only_new_requests(): void
    {
        $receptionist = $this->userWithRole(RoleName::Receptionist);
        $new = ServiceRequest::factory()->create();
        $assigned = ServiceRequest::factory()->assignedTo($this->userWithRole(RoleName::Technician))->create();

        $this->assertTrue($receptionist->can('cancel', $new));
        $this->assertFalse($receptionist->can('cancel', $assigned));
    }

    public function test_an_in_progress_request_offers_the_technician_the_work_actions(): void
    {
        $technician = $this->userWithRole(RoleName::Technician);
        $request = ServiceRequest::factory()->assignedTo($technician)->inStatus(ServiceRequestStatus::InProgress)->create();

        $this->actingAs($technician)->getJson("/api/service-requests/{$request->id}")
            ->assertJsonPath('data.allowed_actions', ['pause', 'manage_parts', 'log_work', 'complete']);
    }

    public function test_filters_and_search_work_together(): void
    {
        $match = ServiceRequest::factory()->overdue()->create();
        ServiceRequest::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->getJson('/api/service-requests?overdue=1&search='.$match->fresh()->code)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.is_overdue', true);
    }

    public function test_guests_cannot_reach_the_control_panel_api(): void
    {
        $this->getJson('/api/service-requests')->assertUnauthorized();
    }
}
