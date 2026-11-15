<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the separation-of-duties rules from the spec.
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, array{RoleName, Permission, bool}>
     */
    public static function matrix(): array
    {
        return [
            'admin assigns technicians' => [RoleName::Admin, Permission::AssignServiceRequests, true],
            'admin cannot issue invoices' => [RoleName::Admin, Permission::ManageInvoices, false],
            'receptionist creates requests' => [RoleName::Receptionist, Permission::CreateServiceRequests, true],
            'receptionist cannot issue invoices' => [RoleName::Receptionist, Permission::ManageInvoices, false],
            'receptionist cannot assign' => [RoleName::Receptionist, Permission::AssignServiceRequests, false],
            'technician works on requests' => [RoleName::Technician, Permission::WorkOnServiceRequests, true],
            'technician cannot see all requests' => [RoleName::Technician, Permission::ViewAllServiceRequests, false],
            'technician cannot issue invoices' => [RoleName::Technician, Permission::ManageInvoices, false],
            'accountant issues invoices' => [RoleName::Accountant, Permission::ManageInvoices, true],
            'accountant cannot assign' => [RoleName::Accountant, Permission::AssignServiceRequests, false],
            'accountant cannot change work status' => [RoleName::Accountant, Permission::WorkOnServiceRequests, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_permissions_match_the_spec(RoleName $role, Permission $permission, bool $expected): void
    {
        $user = User::factory()->create()->assignRole($role->value);

        $this->assertSame($expected, $user->can($permission->value));
    }
}
