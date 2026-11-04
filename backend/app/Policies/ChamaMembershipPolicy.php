<?php

namespace App\Policies;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use App\Support\ChamaPermissions;
use Spatie\Permission\PermissionRegistrar;

class ChamaMembershipPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization);
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

    protected function isActiveMember(User $user, Organization $organization): bool
    {
        return ChamaMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', ChamaMembership::STATUS_ACTIVE)
            ->exists();
    }

    protected function hasPermission(User $user, int $organizationId, string $permission): bool
    {
        $isMember = ChamaMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', ChamaMembership::STATUS_ACTIVE)
            ->exists();

        if (! $isMember) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);

        // See OrganizationPolicy::hasPermission() — avoids spatie/laravel-permission's
        // per-instance role-relation caching returning a different organization's roles.
        return User::find($user->id)->can($permission);
    }
}
