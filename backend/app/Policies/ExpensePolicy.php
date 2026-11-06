<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class ExpensePolicy
{
    use ChecksChamaPermissions;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reverse(User $user, Expense $expense): bool
    {
        return $this->hasPermission($user, $expense->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
