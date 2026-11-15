<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Enums\StockMovementType;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private User $admin;

    private ServiceRequest $request;

    private SparePart $part;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $this->technician = User::factory()->create()->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $this->technician->id]);
        $this->request = ServiceRequest::factory()->assignedTo($this->technician)->inStatus(ServiceRequestStatus::InProgress)->create();
        $this->part = SparePart::factory()->withStock(5)->create(['name' => 'مكثف 35', 'unit_cost' => '95.00', 'unit_price' => '150.00']);
    }

    private function issue(int $quantity, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->technician)
            ->postJson("/api/service-requests/{$this->request->id}/parts", ['spare_part_id' => $this->part->id, 'quantity' => $quantity]);
    }

    public function test_issuing_a_part_deducts_stock_and_snapshots_the_price(): void
    {
        $this->issue(2)
            ->assertCreated()
            ->assertJsonPath('data.parts.0.quantity', 2)
            ->assertJsonPath('data.parts.0.unit_price', '150.00')
            ->assertJsonPath('data.parts.0.line_total', '300.00')
            ->assertJsonMissingPath('data.parts.0.unit_cost');

        $this->assertSame(3, $this->part->fresh()->stock_quantity);
        $movement = $this->part->stockMovements()->sole();
        $this->assertSame(StockMovementType::Usage, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(3, $movement->balance_after);

        $this->part->update(['unit_price' => '999.00']);
        $this->assertSame('150.00', $this->request->parts()->sole()->unit_price);
    }

    public function test_the_technician_cannot_send_a_price(): void
    {
        $this->actingAs($this->technician)
            ->postJson("/api/service-requests/{$this->request->id}/parts", ['spare_part_id' => $this->part->id, 'quantity' => 1, 'unit_price' => '1.00'])
            ->assertCreated();

        $this->assertSame('150.00', $this->request->parts()->sole()->unit_price);
    }

    public function test_insufficient_stock_is_refused_and_nothing_changes(): void
    {
        $this->issue(6)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'الكمية غير متاحة. المتاح من «مكثف 35» في المخزن 5 فقط.');

        $this->assertSame(5, $this->part->fresh()->stock_quantity);
        $this->assertSame(0, $this->request->parts()->count());
        $this->assertSame(0, $this->part->stockMovements()->count());
    }

    public function test_issuing_the_same_part_again_adds_to_the_same_line(): void
    {
        $this->issue(1)->assertCreated();
        $this->issue(2)->assertCreated()->assertJsonCount(1, 'data.parts')->assertJsonPath('data.parts.0.quantity', 3);

        $this->assertSame(2, $this->part->fresh()->stock_quantity);
        $this->assertSame(2, $this->part->stockMovements()->count());
    }

    public function test_returning_a_part_restores_stock(): void
    {
        $this->issue(2);
        $line = $this->request->parts()->sole();

        $this->actingAs($this->technician)->deleteJson("/api/service-requests/{$this->request->id}/parts/{$line->id}")
            ->assertOk()
            ->assertJsonPath('data.parts', []);

        $this->assertSame(5, $this->part->fresh()->stock_quantity);
        $this->assertSame([-2, 2], $this->part->stockMovements()->orderBy('id')->pluck('quantity')->all());
    }

    public function test_a_line_from_another_request_cannot_be_removed_through_this_one(): void
    {
        $this->issue(1);
        $line = $this->request->parts()->sole();
        $other = ServiceRequest::factory()->assignedTo($this->technician)->inStatus(ServiceRequestStatus::InProgress)->create();

        $this->actingAs($this->technician)->deleteJson("/api/service-requests/{$other->id}/parts/{$line->id}")->assertNotFound();
        $this->assertSame(4, $this->part->fresh()->stock_quantity);
    }

    public function test_only_the_assigned_technician_issues_parts_and_only_while_working(): void
    {
        $other = User::factory()->create()->assignRole(RoleName::Technician->value);
        $this->issue(1, $other)->assertForbidden();
        $this->issue(1, $this->admin)->assertForbidden();

        $this->request->forceFill(['status' => ServiceRequestStatus::Completed])->save();
        $this->issue(1)->assertForbidden();

        $this->assertSame(5, $this->part->fresh()->stock_quantity);
    }

    public function test_parts_can_be_issued_while_waiting_for_parts(): void
    {
        $this->request->forceFill(['status' => ServiceRequestStatus::WaitingParts])->save();

        $this->issue(1)->assertCreated();
    }

    public function test_issuing_a_part_clears_an_earlier_no_parts_confirmation(): void
    {
        $this->request->forceFill(['no_parts_confirmed' => true])->save();

        $this->issue(1)->assertJsonPath('data.no_parts_confirmed', false);
    }

    public function test_admin_records_a_purchase_and_a_stocktake_adjustment(): void
    {
        $this->actingAs($this->admin)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'purchase', 'quantity' => 20])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 25)
            ->assertJsonPath('message', 'توريد: الرصيد الآن 25');

        $this->actingAs($this->admin)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'adjustment', 'quantity' => -3])
            ->assertJsonValidationErrors(['note']);

        $this->actingAs($this->admin)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'adjustment', 'quantity' => -3, 'note' => 'تالف بعد الجرد'])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 22);

        $this->actingAs($this->admin)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'adjustment', 'quantity' => -100, 'note' => 'خطأ'])
            ->assertUnprocessable();

        $this->assertSame(22, $this->part->fresh()->stock_quantity);
        $this->assertSame(22, $this->part->stockMovements()->latest('id')->value('balance_after'));
    }

    public function test_a_purchase_cannot_remove_stock(): void
    {
        $this->actingAs($this->admin)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'purchase', 'quantity' => -2])
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_a_new_part_records_its_opening_stock_as_a_movement(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/spare-parts', [
            'sku' => 'pl-vlv-1in', 'name' => 'محبس 1 بوصة', 'unit_cost' => '70', 'unit_price' => '110.50', 'min_stock' => 5, 'opening_stock' => 12,
        ])->assertCreated()->assertJsonPath('data.sku', 'PL-VLV-1IN')->assertJsonPath('data.stock_quantity', 12);

        $part = SparePart::query()->findOrFail($response->json('data.id'));
        $this->assertSame(StockMovementType::Purchase, $part->stockMovements()->sole()->type);
    }

    public function test_stock_cannot_be_edited_directly(): void
    {
        $this->actingAs($this->admin)->putJson("/api/spare-parts/{$this->part->id}", ['stock_quantity' => 999, 'name' => 'مكثف 35 ميكرو'])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 5)
            ->assertJsonPath('data.name', 'مكثف 35 ميكرو');
    }

    public function test_technicians_can_browse_parts_but_not_costs_or_stock_management(): void
    {
        $this->actingAs($this->technician)->getJson('/api/spare-parts?active=1')
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.unit_cost');

        $this->actingAs($this->technician)->postJson("/api/spare-parts/{$this->part->id}/stock", ['type' => 'purchase', 'quantity' => 5])->assertForbidden();
        $this->actingAs($this->technician)->getJson("/api/spare-parts/{$this->part->id}/movements")->assertForbidden();
    }

    public function test_low_stock_parts_are_listed_first_and_counted(): void
    {
        SparePart::factory()->withStock(50)->create(['name' => 'أ قطعة متوفرة', 'min_stock' => 5]);
        SparePart::factory()->withStock(1)->create(['name' => 'ب قطعة ناقصة', 'min_stock' => 5]);

        $this->actingAs($this->admin)->getJson('/api/spare-parts')
            ->assertJsonPath('data.items.0.is_low_stock', true)
            ->assertJsonPath('data.meta.low_stock_count', 2);
    }

    public function test_the_ledger_shows_which_request_used_the_part(): void
    {
        $this->issue(2);

        $this->actingAs($this->admin)->getJson("/api/spare-parts/{$this->part->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.movements.0.type', 'usage')
            ->assertJsonPath('data.movements.0.service_request.code', $this->request->fresh()->code);
    }
}
