<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Admins read invoices; only accountants change them. Separation of duties:
 * the person who assigns and closes jobs is not the person who collects the money.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewInvoices->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ViewInvoices->value);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ManageInvoices->value) && $invoice->acceptsPayments();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ManageInvoices->value) && $invoice->isCancellable();
    }
}
