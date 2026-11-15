<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real concurrency, not a simulation: ten separate PHP processes try to take one unit
 * each from a part that has five. Without SELECT ... FOR UPDATE some of them would read
 * the same balance and the stock would be oversold.
 *
 * Needs a server database shared by all processes, so it only runs on MySQL:
 *   DB_CONNECTION=mysql DB_DATABASE=serviceflow_test php artisan test --group=concurrency
 */
#[Group('concurrency')]
class StockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row locks need MySQL; SQLite locks the whole file.');
        }
    }

    public function test_ten_simultaneous_requests_never_oversell_five_units(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $technician = User::factory()->create()->assignRole(RoleName::Technician->value);
        TechnicianProfile::factory()->create(['user_id' => $technician->id]);
        $part = SparePart::factory()->withStock(5)->create();
        $requests = ServiceRequest::factory()->count(10)->assignedTo($technician)->inStatus(ServiceRequestStatus::InProgress)->create();

        $env = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];

        $results = Process::pool(function ($pool) use ($requests, $part, $technician, $env) {
            foreach ($requests as $request) {
                $pool->path(base_path())->env($env)
                    ->command([PHP_BINARY, 'artisan', 'inventory:race-probe', $request->id, $part->id, $technician->id]);
            }
        })->start()->wait();

        $outcomes = collect($results)->map(fn ($result) => trim($result->output()))->countBy();

        $this->assertSame(5, $outcomes['issued'] ?? 0, 'Exactly five processes should get a unit.');
        $this->assertSame(5, $outcomes['refused'] ?? 0, 'The other five should be refused, not oversold.');
        $this->assertSame(0, $part->fresh()->stock_quantity);
        $this->assertSame(-5, (int) $part->stockMovements()->sum('quantity'));
    }
}
