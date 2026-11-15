<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->receptionist = User::factory()->create()->assignRole(RoleName::Receptionist->value);
    }

    public function test_reception_creates_a_request_for_a_new_customer(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs($this->receptionist)->postJson('/api/service-requests', [
            'customer' => ['name' => 'محمد سامي', 'phone' => '0122 333 4444', 'address' => 'الدقي'],
            'service_category_id' => $category->id,
            'priority' => 'urgent',
            'description' => 'ماس كهربائي في لوحة التوزيع',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.customer.phone', '01223334444')
            ->assertJsonPath('data.created_by', $this->receptionist->name);

        $request = ServiceRequest::query()->sole();
        $this->assertSame(RequestSource::Staff, $request->source);
        $this->assertSame(Priority::Urgent, $request->priority);
    }

    public function test_reception_creates_a_request_for_an_existing_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->receptionist)->postJson('/api/service-requests', [
            'customer_id' => $customer->id,
            'service_category_id' => ServiceCategory::factory()->create()->id,
            'priority' => 'normal',
            'description' => 'الغسالة لا تصرف المياه',
        ])->assertCreated();

        $this->assertSame(1, $customer->serviceRequests()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_a_customer_is_required(): void
    {
        $this->actingAs($this->receptionist)->postJson('/api/service-requests', [
            'service_category_id' => ServiceCategory::factory()->create()->id,
            'priority' => 'normal',
            'description' => 'الغسالة لا تصرف المياه',
        ])->assertJsonPath('errors.customer.0', 'اختر عميلًا موجودًا أو أدخل بيانات عميل جديد.');
    }

    public function test_technicians_cannot_create_requests(): void
    {
        $technician = User::factory()->create()->assignRole(RoleName::Technician->value);

        $this->actingAs($technician)->postJson('/api/service-requests', [
            'customer_id' => Customer::factory()->create()->id,
            'service_category_id' => ServiceCategory::factory()->create()->id,
            'priority' => 'normal',
            'description' => 'الغسالة لا تصرف المياه',
        ])->assertForbidden();
    }

    public function test_raising_the_priority_moves_the_deadline_from_the_received_time(): void
    {
        Carbon::setTestNow('2026-11-15 09:00:00');
        $request = ServiceRequest::factory()->create(['priority' => Priority::Low]);

        Carbon::setTestNow('2026-11-15 11:00:00');
        $this->actingAs($this->receptionist)->putJson("/api/service-requests/{$request->id}", ['priority' => 'urgent'])->assertOk();

        $this->assertEquals(Carbon::parse('2026-11-15 13:00:00'), $request->fresh()->due_at);
    }

    public function test_requests_in_progress_can_no_longer_be_edited_by_reception(): void
    {
        $request = ServiceRequest::factory()->inStatus(ServiceRequestStatus::InProgress)->create();

        $this->actingAs($this->receptionist)->putJson("/api/service-requests/{$request->id}", ['priority' => 'urgent'])->assertForbidden();
    }

    public function test_only_new_requests_can_be_deleted_and_only_by_admins(): void
    {
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $new = ServiceRequest::factory()->create();
        $assigned = ServiceRequest::factory()->inStatus(ServiceRequestStatus::Assigned)->create();

        $this->actingAs($this->receptionist)->deleteJson("/api/service-requests/{$new->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/service-requests/{$assigned->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/service-requests/{$new->id}")->assertOk();

        $this->assertSoftDeleted($new);
    }

    public function test_technician_list_shows_live_workload_to_the_dispatcher_only(): void
    {
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $busy = User::factory()->create(['name' => 'فني مشغول'])->assignRole(RoleName::Technician->value);
        $free = User::factory()->create(['name' => 'فني متاح'])->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $busy->id]);
        TechnicianProfile::factory()->create(['user_id' => $free->id]);
        ServiceRequest::factory()->assignedTo($busy)->count(2)->create();
        ServiceRequest::factory()->assignedTo($busy)->overdue()->create();
        ServiceRequest::factory()->assignedTo($busy)->inStatus(ServiceRequestStatus::Completed)->create();

        $this->actingAs($admin)->getJson('/api/technicians')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'فني متاح')
            ->assertJsonPath('data.1.open_requests_count', 3)
            ->assertJsonPath('data.1.overdue_requests_count', 1);

        $this->actingAs($busy)->getJson('/api/technicians')->assertForbidden();
    }

    public function test_customer_lookup_searches_by_phone_prefix(): void
    {
        Customer::factory()->create(['phone' => '01011112222', 'name' => 'أحمد']);
        Customer::factory()->create(['phone' => '01233334444', 'name' => 'سامي']);

        $this->actingAs($this->receptionist)->getJson('/api/customers?search=0101')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'أحمد');
    }
}
