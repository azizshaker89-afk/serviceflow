<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SparePart;
use App\Models\User;

class SparePartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewSpareParts->value);
    }

    public function view(User $user, SparePart $sparePart): bool
    {
        return $user->can(Permission::ViewSpareParts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSpareParts->value);
    }

    public function update(User $user, SparePart $sparePart): bool
    {
        return $user->can(Permission::ManageSpareParts->value);
    }

    public function adjustStock(User $user, SparePart $sparePart): bool
    {
        return $user->can(Permission::ManageSpareParts->value);
    }

    /**
     * The movement ledger is an audit view for whoever manages stock.
     */
    public function viewMovements(User $user, SparePart $sparePart): bool
    {
        return $user->can(Permission::ManageSpareParts->value);
    }
}
