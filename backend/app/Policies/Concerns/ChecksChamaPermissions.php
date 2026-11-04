<?php

namespace App\Policies\Concerns;

use App\Models\ChamaMembership;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared tenant-isolation primitives for every chama-scoped policy: re-verify the authenticated
 * user's actual, active membership for the specific organization in question (never trust a
 * route-bound id alone — see docs/TENANCY.md), and check a team-scoped permission.
 */
trait ChecksChamaPermissions
{
    protected function isActiveMember(User $user, int $organizationId): bool
    {
        return ChamaMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', ChamaMembership::STATUS_ACTIVE)
            ->exists();
    }

    protected function hasPermission(User $user, int $organizationId, string $permission): bool
    {
        if (! $this->isActiveMember($user, $organizationId)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);

        // spatie/laravel-permission caches the loaded `roles` relation on the model instance and
        // does not reload it when the team (organization) context changes mid-request. Re-fetching
        // a fresh User instance here guarantees this check is evaluated against the organization
        // we just set as the team, not a role set cached from a different organization checked
        // earlier in the request (see OrganizationPolicy's original fix for the full story).
        return User::find($user->id)->can($permission);
    }
}
