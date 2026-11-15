<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->receptionist = User::factory()->create()->assignRole(RoleName::Receptionist->value);
    }

    public function test_reception_adds_and_edits_customers_with_a_unique_phone(): void
    {
        Customer::factory()->create(['phone' => '01011112222']);

        $this->actingAs($this->receptionist)->postJson('/api/customers', ['name' => 'سامح فهمي', 'phone' => '0101 111 2222'])
            ->assertJsonPath('errors.phone.0', 'رقم الموبايل مسجل لعميل آخر.');

        $id = $this->actingAs($this->receptionist)->postJson('/api/customers', ['name' => 'سامح فهمي', 'phone' => '01233334444', 'city' => 'الجيزة'])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->receptionist)->putJson("/api/customers/{$id}", ['phone' => '01233334444', 'address' => 'الهرم'])
            ->assertOk()->assertJsonPath('data.address', 'الهرم');
    }

    public function test_the_list_counts_requests_and_hides_money_from_reception(): void
    {
        $customer = Customer::factory()->create();
        ServiceRequest::factory()->count(2)->for($customer)->create();

        $this->actingAs($this->receptionist)->getJson('/api/customers')
            ->assertJsonPath('data.items.0.service_requests_count', 2)
            ->assertJsonMissingPath('data.items.0.billed_total');

        $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin->value))->getJson('/api/customers')
            ->assertJsonPath('data.items.0.billed_total', '0.00');
    }

    public function test_a_customer_with_open_requests_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        ServiceRequest::factory()->for($customer)->create();

        $this->actingAs($this->receptionist)->deleteJson("/api/customers/{$customer->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لا يمكن حذف عميل لديه طلبات مفتوحة.');
    }

    public function test_technicians_cannot_browse_customers(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RoleName::Technician->value))->getJson('/api/customers')->assertForbidden();
    }
}
