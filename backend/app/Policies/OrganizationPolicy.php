<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

/**
 * Tenant isolation lives here, not in the route: every method re-verifies the authenticated
 * user's actual, active membership (and permission, where relevant) for the specific
 * organization in question — never trusting the organization id just because it resolved via
 * route-model-binding (see docs/TENANCY.md).
 */
class OrganizationPolicy
{
    use ChecksChamaPermissions;

    /**
     * Any authenticated, verified user may create a new chama (Part 8).
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_SETTINGS);
    }

    public function viewAuditLog(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::VIEW_AUDIT_LOG);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_MEMBERS);
    }
}
