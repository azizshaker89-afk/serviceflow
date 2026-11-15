<?php

namespace Tests\Feature;

use App\Actions\ServiceRequests\StartWork;
use App\Actions\ServiceRequests\TransitionServiceRequest;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\SparePart;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ServiceRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $this->technician = $this->makeTechnician('120.00');
    }

    private function makeTechnician(string $rate = '100.00'): User
    {
        $technician = User::factory()->create()->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $technician->id, 'hourly_rate' => $rate]);

        return $technician;
    }

    private function inProgressRequest(): ServiceRequest
    {
        return ServiceRequest::factory()->assignedTo($this->technician)->inStatus(ServiceRequestStatus::InProgress)->create();
    }

    public function test_admin_assigns_a_technician_with_history_assignment_and_activity_log(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $this->technician->id, 'note' => 'قريب من العميل'])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.technician.id', $this->technician->id);

        $request->refresh();
        $this->assertSame(ServiceRequestStatus::Assigned, $request->status);
        $this->assertNotNull($request->assigned_at);
        $this->assertSame(1, $request->assignments()->count());
        $this->assertSame("تعيين: {$this->technician->name}. قريب من العميل", $request->statusHistories()->first()->note);
        $this->assertTrue(Activity::query()->where('event', 'assigned')->where('causer_id', $this->admin->id)->exists());
    }

    public function test_reassigning_closes_the_previous_assignment(): void
    {
        $request = ServiceRequest::factory()->create();
        $other = $this->makeTechnician();

        $this->actingAs($this->admin)->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $this->technician->id])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.technician.id', $other->id);

        $this->assertSame(2, $request->assignments()->count());
        $this->assertSame(1, $request->assignments()->whereNull('unassigned_at')->count());
        $this->assertStringContainsString('إعادة تعيين', $request->statusHistories()->first()->note);
    }

    public function test_assigning_the_same_technician_twice_is_rejected(): void
    {
        $request = ServiceRequest::factory()->assignedTo($this->technician)->create();

        $this->actingAs($this->admin)
            ->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $this->technician->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "الطلب معيّن بالفعل للفني {$this->technician->name}.");
    }

    public function test_only_technicians_can_be_assigned(): void
    {
        $request = ServiceRequest::factory()->create();
        $accountant = User::factory()->create()->assignRole(RoleName::Accountant->value);

        $this->actingAs($this->admin)
            ->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $accountant->id])
            ->assertUnprocessable();

        $this->assertSame(ServiceRequestStatus::New, $request->fresh()->status);
    }

    public function test_technicians_and_accountants_cannot_assign(): void
    {
        $request = ServiceRequest::factory()->create();
        $accountant = User::factory()->create()->assignRole(RoleName::Accountant->value);

        foreach ([$this->technician, $accountant] as $user) {
            $this->actingAs($user)
                ->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $this->technician->id])
                ->assertForbidden();
        }
    }

    public function test_the_assigned_technician_starts_work_and_others_cannot(): void
    {
        $request = ServiceRequest::factory()->assignedTo($this->technician)->create();

        $this->actingAs($this->makeTechnician())->postJson("/api/service-requests/{$request->id}/start")->assertForbidden();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.allowed_actions', ['pause', 'manage_parts', 'log_work', 'complete']);

        $this->assertNotNull($request->fresh()->started_at);
    }

    public function test_a_stale_request_cannot_be_started_after_reassignment(): void
    {
        $request = ServiceRequest::factory()->assignedTo($this->technician)->create();
        $stale = ServiceRequest::query()->find($request->id);

        $request->forceFill(['assigned_technician_id' => $this->makeTechnician()->id])->save();

        $this->expectException(BusinessRuleException::class);
        app(StartWork::class)->handle($stale, $this->technician);
    }

    public function test_the_state_machine_rejects_illegal_transitions_even_when_called_directly(): void
    {
        $request = ServiceRequest::factory()->inStatus(ServiceRequestStatus::Completed)->create();

        try {
            app(TransitionServiceRequest::class)->handle($request, ServiceRequestStatus::InProgress, $this->admin);
            $this->fail('Expected the transition to be rejected.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('لا يمكن نقل الطلب من «تمت الصيانة» إلى «جاري التنفيذ».', $e->getMessage());
        }

        $this->assertSame(ServiceRequestStatus::Completed, $request->fresh()->status);
        $this->assertSame(0, $request->statusHistories()->count());
    }

    public function test_pause_requires_a_reason_and_resume_continues_the_work(): void
    {
        $request = $this->inProgressRequest();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/pause", [])
            ->assertJsonValidationErrors(['waiting_for']);

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/pause", ['waiting_for' => 'موتور مروحة'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_parts')
            ->assertJsonPath('data.allowed_actions', ['resume', 'manage_parts']);

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_work_is_logged_with_a_snapshot_of_the_hourly_rate(): void
    {
        $request = $this->inProgressRequest();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/work-logs", ['hours' => 1.5, 'note' => 'تغيير مكثف'])
            ->assertCreated()
            ->assertJsonPath('data.work_logs.0.hours', '1.50')
            ->assertJsonPath('data.totals.hours', '1.50');

        $this->technician->technicianProfile->update(['hourly_rate' => '200.00']);

        $this->assertSame('120.00', $request->workLogs()->sole()->hourly_rate);
    }

    public function test_hours_must_be_in_quarter_hour_steps(): void
    {
        $request = $this->inProgressRequest();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/work-logs", ['hours' => 1.1])
            ->assertJsonValidationErrors(['hours']);
    }

    public function test_completion_requires_logged_work(): void
    {
        $request = $this->inProgressRequest();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/complete", ['no_parts_confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'سجّل ساعات العمل قبل إنهاء الصيانة.');

        $this->assertSame(ServiceRequestStatus::InProgress, $request->fresh()->status);
    }

    public function test_completion_requires_parts_or_an_explicit_no_parts_confirmation(): void
    {
        $request = $this->inProgressRequest();
        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/work-logs", ['hours' => 2])->assertCreated();

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'سجّل قطع الغيار المستخدمة، أو أكّد أن الصيانة لم تحتج أي قطع.');

        $this->actingAs($this->technician)->postJson("/api/service-requests/{$request->id}/complete", ['no_parts_confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.no_parts_confirmed', true);

        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_completed_request_hands_over_to_the_accountant(): void
    {
        $request = ServiceRequest::factory()->assignedTo($this->technician)->inStatus(ServiceRequestStatus::Completed)->create();
        $accountant = User::factory()->create()->assignRole(RoleName::Accountant->value);

        $this->actingAs($accountant)->getJson("/api/service-requests/{$request->id}")
            ->assertJsonPath('data.allowed_actions', ['issue_invoice']);
    }

    public function test_cancelling_returns_issued_parts_to_stock_in_the_same_transaction(): void
    {
        $request = $this->inProgressRequest();
        $part = SparePart::factory()->withStock(8)->create();
        ServiceRequestPart::query()->create([
            'service_request_id' => $request->id, 'spare_part_id' => $part->id, 'quantity' => 2,
            'unit_cost' => $part->unit_cost, 'unit_price' => $part->unit_price, 'added_by' => $this->technician->id,
        ]);

        $this->actingAs($this->admin)->postJson("/api/service-requests/{$request->id}/cancel", ['reason' => 'العميل اعتذر عن الزيارة'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'العميل اعتذر عن الزيارة')
            ->assertJsonPath('data.parts', []);

        $this->assertSame(10, $part->fresh()->stock_quantity);
        $movement = $part->stockMovements()->sole();
        $this->assertSame(StockMovementType::Return, $movement->type);
        $this->assertSame(2, $movement->quantity);
        $this->assertSame(10, $movement->balance_after);
        $this->assertTrue($movement->reference->is($request));
    }

    public function test_reception_cancels_new_requests_only(): void
    {
        $receptionist = User::factory()->create()->assignRole(RoleName::Receptionist->value);
        $assigned = ServiceRequest::factory()->assignedTo($this->technician)->create();
        $new = ServiceRequest::factory()->create();

        $this->actingAs($receptionist)->postJson("/api/service-requests/{$assigned->id}/cancel", ['reason' => 'طلب مكرر'])->assertForbidden();
        $this->actingAs($receptionist)->postJson("/api/service-requests/{$new->id}/cancel", ['reason' => 'طلب مكرر'])->assertOk();
    }

    public function test_cancelled_requests_accept_no_further_actions(): void
    {
        $request = ServiceRequest::factory()->inStatus(ServiceRequestStatus::Cancelled)->create();

        $this->actingAs($this->admin)->getJson("/api/service-requests/{$request->id}")->assertJsonPath('data.allowed_actions', []);
        $this->actingAs($this->admin)->postJson("/api/service-requests/{$request->id}/assign", ['technician_id' => $this->technician->id])->assertForbidden();
    }
}
