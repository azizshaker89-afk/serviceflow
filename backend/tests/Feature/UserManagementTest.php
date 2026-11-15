<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ServiceRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create()->assignRole(RoleName::Admin->value);
    }

    public function test_admin_creates_a_technician_with_a_profile(): void
    {
        $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'رامي عادل', 'email' => 'rami@serviceflow.test', 'password' => 'secret123', 'role' => 'technician',
        ])->assertJsonValidationErrors(['specialization', 'hourly_rate']);

        $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'رامي عادل', 'email' => 'rami@serviceflow.test', 'password' => 'secret123', 'role' => 'technician',
            'specialization' => 'كهرباء', 'hourly_rate' => '110',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'technician')
            ->assertJsonPath('data.hourly_rate', '110.00');

        $this->assertTrue(User::query()->where('email', 'rami@serviceflow.test')->sole()->isTechnician());
    }

    public function test_weak_passwords_are_refused(): void
    {
        $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'منى', 'email' => 'mona2@serviceflow.test', 'password' => 'password', 'role' => 'receptionist',
        ])->assertJsonValidationErrors(['password']);
    }

    public function test_a_technician_with_open_jobs_cannot_be_deactivated(): void
    {
        $technician = User::factory()->create()->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $technician->id]);
        ServiceRequest::factory()->assignedTo($technician)->create();

        $this->actingAs($this->admin)->putJson("/api/users/{$technician->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لدى الفني 1 طلبات مفتوحة. أعد تعيينها لفني آخر أولًا.');
    }

    public function test_admins_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin)->putJson("/api/users/{$this->admin->id}", ['is_active' => false])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson("/api/users/{$this->admin->id}", ['role' => 'receptionist'])->assertUnprocessable();
    }

    public function test_a_deactivated_account_can_no_longer_log_in(): void
    {
        $receptionist = User::factory()->create(['email' => 'old@serviceflow.test'])->assignRole(RoleName::Receptionist->value);

        $this->actingAs($this->admin)->putJson("/api/users/{$receptionist->id}", ['is_active' => false])->assertOk();

        auth()->guard('web')->logout();
        $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/login', ['email' => 'old@serviceflow.test', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_only_admins_manage_users(): void
    {
        foreach ([RoleName::Receptionist, RoleName::Technician, RoleName::Accountant] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role->value))->getJson('/api/users')->assertForbidden();
        }
    }
}
