<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class FinancialAccountPolicy
{
    use ChecksChamaPermissions;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->isActiveMember($user, $account->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reconcile(User $user, FinancialAccount $account): bool
    {
        return $this->hasPermission($user, $account->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
