<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_there_is_a_demo_account_for_every_role(): void
    {
        foreach (RoleName::cases() as $role) {
            $this->assertTrue(User::role($role->value)->exists(), "Missing demo account for {$role->value}");
        }
    }

    public function test_every_technician_has_a_profile(): void
    {
        $this->assertSame(0, User::technicians()->doesntHave('technicianProfile')->count());
    }

    public function test_stock_balance_matches_the_movement_ledger(): void
    {
        SparePart::query()->withSum('stockMovements', 'quantity')->get()
            ->each(fn (SparePart $part) => $this->assertSame(
                $part->stock_quantity,
                (int) $part->stock_movements_sum_quantity,
                "Ledger mismatch for {$part->sku}",
            ));
    }

    public function test_every_seeded_request_has_its_initial_history_entry(): void
    {
        $this->assertSame(0, ServiceRequest::query()->doesntHave('statusHistories')->count());
    }
}
