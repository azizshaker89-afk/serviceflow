<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ServiceRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_who_did_what_with_a_link_to_the_record(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['name' => 'أحمد'])->assignRole(RoleName::Admin->value);
        $technician = User::factory()->create()->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $technician->id]);
        $request = ServiceRequest::factory()->create();

        $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $technician->id])->assertOk();

        $this->actingAs($admin)->getJson('/api/activity-logs?group=service-requests')
            ->assertOk()
            ->assertJsonPath('data.items.0.causer', 'أحمد')
            ->assertJsonPath('data.items.0.event', 'assigned')
            ->assertJsonPath('data.items.0.subject.type', 'service_request')
            ->assertJsonPath('data.items.0.subject.id', $request->id);

        $this->actingAs(User::factory()->create()->assignRole(RoleName::Accountant->value))->getJson('/api/activity-logs')->assertForbidden();
    }
}
