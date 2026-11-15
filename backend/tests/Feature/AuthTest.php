<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_staff_can_log_in_and_receive_role_and_permissions(): void
    {
        User::factory()->create(['email' => 'tech@test.com'])->assignRole(RoleName::Technician->value);

        $this->postJson('/api/auth/login', ['email' => 'tech@test.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'technician')
            ->assertJsonPath('data.role_label', 'فني صيانة')
            ->assertJsonFragment(['service-requests.work']);

        $this->assertAuthenticated('web');
    }

    public function test_wrong_password_returns_unified_validation_error(): void
    {
        User::factory()->create(['email' => 'admin@test.com']);

        $this->postJson('/api/auth/login', ['email' => 'admin@test.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'البيانات المدخلة غير صحيحة',
                'errors' => ['email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.']],
            ]);

        $this->assertGuest('web');
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'old@test.com', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'old@test.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'هذا الحساب موقوف. تواصل مع مدير النظام.');

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'admin@test.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'admin@test.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'admin@test.com', 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertJsonPath('success', false);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'يجب تسجيل الدخول أولًا');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->actingAs($user)->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest('web');
    }
}
