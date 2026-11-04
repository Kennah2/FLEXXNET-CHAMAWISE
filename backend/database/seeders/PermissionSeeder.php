<?php

namespace Database\Seeders;

use App\Support\ChamaPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the platform-wide permission catalog (Part 10). Per-chama roles are provisioned
 * separately, per organization, by ChamaRoleProvisioner when each chama is created.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ChamaPermissions::ALL as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}
