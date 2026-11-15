<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_figures_come_from_real_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->count(2)->create()->each->assignRole(RoleName::Technician->value);

        Carbon::setTestNow('2026-11-10 10:00');
        $fast = ServiceRequest::factory()->inStatus(ServiceRequestStatus::Completed)->create(['due_at' => '2026-11-10 14:00']);
        $slow = ServiceRequest::factory()->inStatus(ServiceRequestStatus::Completed)->create(['due_at' => '2026-11-10 14:00']);
        ServiceRequest::factory()->create();
        Carbon::setTestNow();

        $fast->forceFill(['completed_at' => '2026-11-10 12:00'])->save();
        $slow->forceFill(['completed_at' => '2026-11-10 16:00'])->save();

        $this->getJson('/api/public/stats')
            ->assertOk()
            ->assertJsonPath('data.completed_requests', 2)
            ->assertJsonPath('data.average_completion_hours', 4)
            ->assertJsonPath('data.on_time_rate', 50)
            ->assertJsonPath('data.technicians', 2)
            ->assertJsonPath('data.sla_hours.urgent', 4);
    }
}
