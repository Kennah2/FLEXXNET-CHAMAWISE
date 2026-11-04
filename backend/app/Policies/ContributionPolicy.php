<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class ContributionPolicy
{
    use ChecksChamaPermissions;

    /**
     * Any active member may record a contribution — for themselves always, or for another
     * member only if they hold the manage-finances permission (a treasurer entering a cash
     * payment on a member's behalf).
     */
    public function create(User $user, Organization $organization, int $forUserId): bool
    {
        if (! $this->isActiveMember($user, $organization->id)) {
            return false;
        }

        if ($forUserId === $user->id) {
            return true;
        }

        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function view(User $user, Contribution $contribution): bool
    {
        if ($user->id === $contribution->user_id) {
            return true;
        }

        return $this->hasPermission($user, $contribution->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function verify(User $user, Contribution $contribution): bool
    {
        return $this->hasPermission($user, $contribution->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reject(User $user, Contribution $contribution): bool
    {
        return $this->hasPermission($user, $contribution->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reverse(User $user, Contribution $contribution): bool
    {
        return $this->hasPermission($user, $contribution->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
