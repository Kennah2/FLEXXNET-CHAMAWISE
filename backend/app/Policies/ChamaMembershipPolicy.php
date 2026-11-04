<?php

namespace App\Policies;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class ChamaMembershipPolicy
{
    use ChecksChamaPermissions;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function view(User $user, ChamaMembership $membership): bool
    {
        if ($user->id === $membership->user_id) {
            return true;
        }

        return $this->hasPermission($user, $membership->organization_id, ChamaPermissions::MANAGE_MEMBERS);
    }

    public function updateStatus(User $user, ChamaMembership $membership): bool
    {
        return $this->hasPermission($user, $membership->organization_id, ChamaPermissions::MANAGE_MEMBERS);
    }
}
