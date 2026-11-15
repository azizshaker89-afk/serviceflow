<?php

namespace App\Enums;

/**
 * Every permission in the system. Policies reference these cases instead of raw strings,
 * so a typo is a compile error rather than a silent "access denied".
 */
enum Permission: string
{
    case ManageUsers = 'users.manage';
    case ViewActivityLogs = 'activity-logs.view';

    case ViewCustomers = 'customers.view';
    case ManageCustomers = 'customers.manage';
    case ManageServiceCategories = 'service-categories.manage';

    case ViewAllServiceRequests = 'service-requests.view-all';
    case CreateServiceRequests = 'service-requests.create';
    case UpdateServiceRequests = 'service-requests.update';
    case AssignServiceRequests = 'service-requests.assign';
    case CancelServiceRequests = 'service-requests.cancel';
    case WorkOnServiceRequests = 'service-requests.work';

    case ViewSpareParts = 'spare-parts.view';
    case ManageSpareParts = 'spare-parts.manage';

    case ViewInvoices = 'invoices.view';
    case ManageInvoices = 'invoices.manage';

    case ViewOperationalReports = 'reports.operational';
    case ViewFinancialReports = 'reports.financial';

    /**
     * @return list<self>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => array_values(array_filter(
                self::cases(),
                fn (self $permission) => $permission !== self::WorkOnServiceRequests && $permission !== self::ManageInvoices,
            )),
            RoleName::Receptionist => [
                self::ViewCustomers, self::ManageCustomers,
                self::ViewAllServiceRequests, self::CreateServiceRequests,
                self::UpdateServiceRequests, self::CancelServiceRequests,
            ],
            RoleName::Technician => [
                self::WorkOnServiceRequests, self::ViewSpareParts,
            ],
            RoleName::Accountant => [
                self::ViewAllServiceRequests, self::ViewCustomers,
                self::ViewSpareParts, self::ViewInvoices, self::ManageInvoices,
                self::ViewFinancialReports,
            ],
        };
    }
}
