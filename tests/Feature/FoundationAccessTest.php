<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\BloggerProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FoundationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->has('stats')
                ->where('foundation.paymentProvider', 'Paddle'),
            );
    }

    public function test_sub_admin_requires_assigned_permission_for_admin_sections(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SubAdmin->value);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $user->givePermissionTo(
            Permission::findByName(PermissionName::ManageUsers->value, 'web'),
        );

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('title', 'Users'),
            );
    }

    public function test_creator_is_redirected_to_creator_dashboard_regardless_of_approval_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->create([
            'user_id' => $user->id,
            'status' => BloggerStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('creator.dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/Dashboard')
                ->where('profile.blogger_status', BloggerStatus::Pending->value),
            );
    }

    public function test_student_can_access_student_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Student->value);
        $user->studentProfile()->firstOrCreate();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Dashboard'),
            );
    }
}
