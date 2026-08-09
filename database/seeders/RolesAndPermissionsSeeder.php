<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
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

        foreach (PermissionName::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleName::values() as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', PermissionName::values())
            ->get();

        Role::findByName(RoleName::SuperAdmin->value, 'web')
            ->syncPermissions($permissions);

        Role::findByName(RoleName::SubAdmin->value, 'web')
            ->syncPermissions([]);

        Role::findByName(RoleName::Blogger->value, 'web')
            ->syncPermissions([]);

        Role::findByName(RoleName::Student->value, 'web')
            ->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
