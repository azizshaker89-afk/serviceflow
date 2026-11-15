<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\Invoice;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private ServiceRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('tax_rate', '14');
        $this->accountant = User::factory()->create()->assignRole(RoleName::Accountant->value);

        $technician = User::factory()->create(['name' => 'محمود حسن'])->assignRole(RoleName::Technician->value);
        $this->request = ServiceRequest::factory()
            ->for(ServiceCategory::factory()->state(['default_fee' => null]), 'category')
            ->assignedTo($technician)
            ->inStatus(ServiceRequestStatus::Completed)
            ->create();

        $part = SparePart::factory()->create(['name' => 'فريون R410']);
        ServiceRequestPart::query()->create([
            'service_request_id' => $this->request->id, 'spare_part_id' => $part->id, 'quantity' => 2,
            'unit_cost' => '180.00', 'unit_price' => '260.00', 'added_by' => $technician->id,
        ]);
        WorkLog::factory()->create([
            'service_request_id' => $this->request->id, 'technician_id' => $technician->id, 'hours' => '2.50', 'hourly_rate' => '120.00',
        ]);
    }

    private function issue(array $extra = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->accountant)->postJson('/api/invoices', ['service_request_id' => $this->request->id, ...$extra]);
    }

    public function test_the_accountant_issues_an_invoice_with_explained_lines(): void
    {
        $this->issue(['discount' => '20'])
            ->assertCreated()
            ->assertJsonPath('data.parts_total', '520.00')
            ->assertJsonPath('data.labor_total', '300.00')
            ->assertJsonPath('data.subtotal', '820.00')
            ->assertJsonPath('data.discount', '20.00')
            ->assertJsonPath('data.tax_amount', '112.00')
            ->assertJsonPath('data.total', '912.00')
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.items.0.description', 'فريون R410')
            ->assertJsonPath('data.items.1.description', 'مصنعية: محمود حسن')
            ->assertJsonPath('data.allowed_actions', ['record_payment', 'cancel']);

        $invoice = Invoice::query()->sole();
        $this->assertSame(sprintf('INV-%s-%05d', now()->format('Y'), $invoice->id), $invoice->invoice_number);
        $this->assertSame('360.00', $invoice->parts_cost);
    }

    public function test_preview_returns_the_same_numbers_without_saving(): void
    {
        $this->actingAs($this->accountant)->getJson("/api/service-requests/{$this->request->id}/invoice-preview?discount=20")
            ->assertOk()
            ->assertJsonPath('data.total', '912.00');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_the_tax_rate_comes_from_settings_not_the_request(): void
    {
        Setting::set('tax_rate', '0');

        $this->issue(['tax_rate' => '50'])->assertJsonPath('data.tax_amount', '0.00')->assertJsonPath('data.total', '820.00');
    }

    public function test_a_discount_above_the_subtotal_is_refused(): void
    {
        $this->issue(['discount' => '900'])->assertUnprocessable();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_no_invoice_before_the_work_is_completed(): void
    {
        $this->request->forceFill(['status' => ServiceRequestStatus::InProgress])->save();

        $this->issue()->assertForbidden();
    }

    public function test_a_request_cannot_have_two_active_invoices(): void
    {
        $this->issue()->assertCreated();
        $this->issue()->assertForbidden();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_only_accountants_issue_invoices(): void
    {
        foreach ([RoleName::Admin, RoleName::Receptionist, RoleName::Technician] as $role) {
            $this->issue([], User::factory()->create()->assignRole($role->value))->assertForbidden();
        }
    }

    public function test_payments_drive_the_invoice_status(): void
    {
        $id = $this->issue()->json('data.id');

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '500', 'method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.balance_due', '434.80')
            ->assertJsonPath('data.allowed_actions', ['record_payment']);

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '434.80', 'method' => 'wallet', 'reference' => 'TX-99'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.allowed_actions', [])
            ->assertJsonCount(2, 'data.payments');
    }

    public function test_overpaying_is_refused(): void
    {
        $id = $this->issue()->json('data.id');

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '934.81', 'method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'المبلغ أكبر من المتبقي على الفاتورة (934.80 ج.م).');
    }

    public function test_a_paid_invoice_accepts_no_payment_and_cannot_be_cancelled(): void
    {
        $id = $this->issue()->json('data.id');
        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '934.80', 'method' => 'card'])->assertOk();

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '1', 'method' => 'cash'])->assertForbidden();
        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/cancel", ['reason' => 'خطأ في الخصم'])->assertForbidden();
    }

    public function test_cancelling_an_unpaid_invoice_allows_a_corrected_one(): void
    {
        $first = $this->issue()->json('data.id');

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$first}/cancel", ['reason' => 'نسيت الخصم المتفق عليه'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->issue(['discount' => '50'])->assertCreated()->assertJsonPath('data.total', '877.80');

        $this->assertSame(2, $this->request->invoices()->count());
        $this->assertSame('877.80', $this->request->fresh()->invoice->total);
    }

    public function test_an_invoice_with_payments_cannot_be_cancelled(): void
    {
        $id = $this->issue()->json('data.id');
        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/payments", ['amount' => '100', 'method' => 'cash']);

        $this->actingAs($this->accountant)->postJson("/api/invoices/{$id}/cancel", ['reason' => 'خطأ في الخصم'])->assertForbidden();
    }

    public function test_admins_read_invoices_but_cannot_collect(): void
    {
        $id = $this->issue()->json('data.id');
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->getJson("/api/invoices/{$id}")->assertOk()->assertJsonPath('data.allowed_actions', []);
        $this->actingAs($admin)->postJson("/api/invoices/{$id}/payments", ['amount' => '10', 'method' => 'cash'])->assertForbidden();
    }

    public function test_technicians_see_no_invoices(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RoleName::Technician->value))->getJson('/api/invoices')->assertForbidden();
    }

    public function test_the_list_summarises_what_is_outstanding_and_what_is_waiting_to_be_billed(): void
    {
        $this->actingAs($this->accountant)->getJson('/api/invoices')
            ->assertJsonPath('data.summary.awaiting_invoice_count', 1)
            ->assertJsonPath('data.summary.outstanding_count', 0);

        $this->issue();

        $this->actingAs($this->accountant)->getJson('/api/invoices?outstanding=1')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.summary.awaiting_invoice_count', 0)
            ->assertJsonPath('data.summary.outstanding_count', 1);
    }

    public function test_the_customer_sees_the_invoice_on_the_tracking_page(): void
    {
        $this->issue();
        $request = $this->request->fresh('customer');

        $this->postJson('/api/public/service-requests/track', ['code' => $request->code, 'phone' => $request->customer->phone])
            ->assertJsonPath('data.invoice.total', '934.80')
            ->assertJsonPath('data.invoice.status', 'unpaid')
            ->assertJsonMissingPath('data.invoice.parts_cost');
    }
}
