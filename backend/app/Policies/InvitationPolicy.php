<?php

namespace App\Policies;

use App\Models\ChamaMembership;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Support\ChamaPermissions;
use Spatie\Permission\PermissionRegistrar;

class InvitationPolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_MEMBERS);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->hasPermission($user, $invitation->organization_id, ChamaPermissions::MANAGE_MEMBERS);
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
