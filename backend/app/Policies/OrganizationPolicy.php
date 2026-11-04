<?php

namespace App\Policies;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use App\Support\ChamaPermissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant isolation lives here, not in the route: every method re-verifies the authenticated
 * user's actual, active membership (and permission, where relevant) for the specific
 * organization in question — never trusting the organization id just because it resolved via
 * route-model-binding (see docs/TENANCY.md).
 */
class OrganizationPolicy
{
    /**
     * Any authenticated, verified user may create a new chama (Part 8).
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, ChamaPermissions::MANAGE_SETTINGS);
    }

    public function viewAuditLog(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, ChamaPermissions::VIEW_AUDIT_LOG);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, ChamaPermissions::MANAGE_MEMBERS);
    }

    protected function isActiveMember(User $user, Organization $organization): bool
    {
        return ChamaMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', ChamaMembership::STATUS_ACTIVE)
            ->exists();
    }

    protected function hasPermission(User $user, Organization $organization, string $permission): bool
    {
        if (! $this->isActiveMember($user, $organization)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        // spatie/laravel-permission caches the loaded `roles` relation on the model instance
        // and does not reload it when the team context changes mid-request (hasRole() calls
        // loadMissing('roles'), a no-op once already loaded). Re-fetching a fresh instance here
        // guarantees this check is evaluated against the organization we just set as the team,
        // not a role set cached from a different organization checked earlier in the request.
        return User::find($user->id)->can($permission);
    }
}
