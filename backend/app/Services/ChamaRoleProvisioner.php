<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\ChamaPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the default set of per-chama roles (Part 10) the first time a chama is created, and
 * assigns the founding member their starting role. Roles are created team-scoped to the new
 * organization (spatie/laravel-permission "teams" feature, team key = organization_id), so two
 * chamas each get their own independent "chairperson" row a chama can later rename or extend
 * with custom roles without affecting any other chama.
 */
class ChamaRoleProvisioner
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function provision(Organization $organization, User $founder, string $founderRole = 'chairperson'): void
    {
        $this->registrar->setPermissionsTeamId($organization->id);

        foreach (ChamaPermissions::DEFAULT_ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'organization_id' => $organization->id,
            ]);

            $permissions = Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissions);
        }

        $founder->assignRole($founderRole);
    }
}
