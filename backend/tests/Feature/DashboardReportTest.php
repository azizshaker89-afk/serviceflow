<?php

namespace Tests\Feature;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Models\WorkLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('tax_rate', '0');
        Carbon::setTestNow('2026-11-15 12:00:00');

        $this->admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $this->accountant = User::factory()->create()->assignRole(RoleName::Accountant->value);
        $this->technician = User::factory()->create(['name' => 'كريم'])->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $this->technician->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A completed job received at $receivedAt, finished $hours later, billed at $amount.
     */
    private function completedJob(string $receivedAt, int $hours, string $amount, Priority $priority = Priority::Normal): ServiceRequest
    {
        Carbon::setTestNow($receivedAt);
        $category = ServiceCategory::factory()->create(['name' => 'تكييف '.uniqid(), 'default_fee' => $amount]);
        $request = ServiceRequest::factory()->for($category, 'category')->assignedTo($this->technician)
            ->inStatus(ServiceRequestStatus::Completed)->create(['priority' => $priority]);
        $request->forceFill(['completed_at' => Carbon::parse($receivedAt)->addHours($hours)])->save();
        WorkLog::factory()->create(['service_request_id' => $request->id, 'technician_id' => $this->technician->id]);
        Carbon::setTestNow('2026-11-15 12:00:00');

        return $request;
    }

    public function test_operational_figures_are_computed_from_the_data(): void
    {
        $this->completedJob('2026-11-10 09:00', 4, '200');
        $this->completedJob('2026-11-12 09:00', 60, '300');
        ServiceRequest::factory()->create();
        ServiceRequest::factory()->assignedTo($this->technician)->overdue()->create();

        $data = $this->actingAs($this->admin)->getJson('/api/reports/dashboard')->assertOk()->json('data.operations');

        $this->assertSame(1, $data['kpis']['unassigned']);
        $this->assertSame(1, $data['kpis']['overdue']);
        $this->assertSame(2, $data['kpis']['open']);
        $this->assertSame(2, $data['kpis']['completed_in_period']);
        $this->assertEquals(32, $data['average_completion_hours']);
        $this->assertSame(50, $data['on_time_rate']);
        $this->assertSame('كريم', $data['technicians'][0]['name']);
        $this->assertSame(2, $data['technicians'][0]['completed']);
        $this->assertSame(1, $data['technicians'][0]['overdue']);
    }

    public function test_money_figures_follow_payments_and_invoices(): void
    {
        $job = $this->completedJob('2026-11-10 09:00', 4, '200');
        $invoice = app(IssueInvoice::class)->handle($job, $this->accountant);
        app(RecordPayment::class)->handle($invoice, '150.00', PaymentMethod::Cash, $this->accountant);
        $this->completedJob('2026-11-11 09:00', 4, '300');

        $finance = $this->actingAs($this->accountant)->getJson('/api/reports/dashboard')->assertOk()->json('data.finance');

        $this->assertSame('150.00', $finance['kpis']['collected_in_period']);
        $this->assertSame('200.00', $finance['kpis']['invoiced_in_period']);
        $this->assertSame('50.00', $finance['kpis']['outstanding']);
        $this->assertSame(1, $finance['kpis']['awaiting_invoice']);

        $technicians = $this->actingAs($this->admin)->getJson('/api/reports/dashboard')->assertOk()->json('data.operations.technicians');
        $this->assertSame('200.00', $technicians[0]['revenue']);
        $this->assertSame(['month' => '2026-11', 'collected' => '150.00', 'invoiced' => '200.00'], end($finance['monthly_revenue']));
        $this->assertCount(6, $finance['monthly_revenue']);
    }

    public function test_each_role_gets_only_its_sections(): void
    {
        $this->actingAs($this->accountant)->getJson('/api/reports/dashboard')
            ->assertJsonMissingPath('data.operations')
            ->assertJsonStructure(['data' => ['finance']]);

        $this->actingAs($this->admin)->getJson('/api/reports/dashboard')
            ->assertJsonStructure(['data' => ['operations', 'finance']]);

        $this->actingAs($this->technician)->getJson('/api/reports/dashboard')->assertForbidden();
    }

    public function test_the_period_filter_limits_period_figures(): void
    {
        $this->completedJob('2026-09-01 09:00', 4, '200');
        $this->completedJob('2026-11-10 09:00', 4, '200');

        $this->actingAs($this->admin)->getJson('/api/reports/dashboard?from=2026-08-01&to=2026-09-30')
            ->assertJsonPath('data.operations.kpis.completed_in_period', 1)
            ->assertJsonPath('data.period.from', '2026-08-01');
    }

    public function test_daily_intake_is_zero_filled(): void
    {
        ServiceRequest::factory()->count(3)->create();

        $days = $this->actingAs($this->admin)->getJson('/api/reports/dashboard?from=2026-11-13&to=2026-11-15')->json('data.operations.daily_intake');

        $this->assertSame([['date' => '2026-11-13', 'count' => 0], ['date' => '2026-11-14', 'count' => 0], ['date' => '2026-11-15', 'count' => 3]], $days);
    }
}
