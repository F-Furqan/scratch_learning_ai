<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ContentSeeder::class,
            AdsSeeder::class,
            PaymentsSeeder::class,
            LearningExperienceSeeder::class,
            CreatorEditorialSeeder::class,
            CommunitySeeder::class,
            GrowthMonetizationSeeder::class,
            AnalyticsReportingSeeder::class,
        ]);

        $student = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );

        $student->assignRole(RoleName::Student->value);
        $student->studentProfile()->firstOrCreate();
    }
}
