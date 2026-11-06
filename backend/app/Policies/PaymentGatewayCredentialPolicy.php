<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class PaymentGatewayCredentialPolicy
{
    use ChecksChamaPermissions;

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }
}
