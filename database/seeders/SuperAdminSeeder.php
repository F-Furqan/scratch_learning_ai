<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the first super administrator.
     */
    public function run(): void
    {
        $email = (string) config('platform.super_admin.email', 'admin@example.com');
        $password = config('platform.super_admin.password');

        if (app()->isProduction() && blank($password)) {
            throw new \RuntimeException('SUPER_ADMIN_PASSWORD must be set before seeding production.');
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('platform.super_admin.name', 'Super Admin'),
                'password' => Hash::make(is_string($password) && filled($password) ? $password : 'password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole(RoleName::SuperAdmin->value);
    }
}
