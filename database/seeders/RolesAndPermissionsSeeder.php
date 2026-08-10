<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Modules\Admin\Registry\AdminResourceRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the platform roles and permission catalog.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = array_values(array_unique([
            ...PermissionName::values(),
            ...app(AdminResourceRegistry::class)->permissionNames(),
        ]));

        foreach ($permissionNames as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleName::values() as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->get();

        Role::findByName(RoleName::SuperAdmin->value, 'web')
            ->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
