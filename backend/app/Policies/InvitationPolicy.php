<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class InvitationPolicy
{
    use ChecksChamaPermissions;

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_MEMBERS);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->hasPermission($user, $invitation->organization_id, ChamaPermissions::MANAGE_MEMBERS);
    }
}
