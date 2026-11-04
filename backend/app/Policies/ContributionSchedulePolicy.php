<?php

namespace App\Policies;

use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class ContributionSchedulePolicy
{
    use ChecksChamaPermissions;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function view(User $user, ContributionSchedule $schedule): bool
    {
        return $this->isActiveMember($user, $schedule->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }
}
