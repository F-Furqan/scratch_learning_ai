<?php

namespace App\Services\Identity;

use App\Enums\BloggerStatus;
use App\Enums\RoleName;
use App\Models\BloggerProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserProfileProvisioner
{
    public function provisionStudent(User $user): StudentProfile
    {
        $role = Role::findOrCreate(RoleName::Student->value, 'web');
        $user->assignRole($role);

        return StudentProfile::firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function provisionBlogger(User $user, array $attributes = []): BloggerProfile
    {
        $role = Role::findOrCreate(RoleName::Blogger->value, 'web');
        $user->assignRole($role);

        return BloggerProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $attributes['phone'] ?? null,
                'profile_photo_path' => $attributes['profile_photo_path'] ?? null,
                'bio' => $attributes['bio'] ?? null,
                'expertise' => $attributes['expertise'] ?? null,
                'linkedin_url' => $attributes['linkedin_url'] ?? null,
                'website_url' => $attributes['website_url'] ?? null,
                'application_reason' => $attributes['application_reason'] ?? null,
                'status' => $attributes['status'] ?? BloggerStatus::Pending,
            ],
        );
    }
}
