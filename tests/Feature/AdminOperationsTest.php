<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\BloggerProfile;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_admin_users_screen_renders_operations_console(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('resource', 'users')
                ->where('title', 'Users')
                ->has('columns')
                ->has('fields')
                ->has('rows.data'),
            );
    }

    public function test_admin_can_create_user_and_assign_role_with_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Operations Student',
                'email' => 'ops-student@example.com',
                'password' => 'password',
                'status' => 'active',
                'roles' => [RoleName::Student->value],
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'ops-student@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::Student->value));
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_id' => $this->admin->id,
            'action' => 'admin.users.created',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_admin_bulk_publish_course_records_audit_and_approval_history(): void
    {
        $course = Course::factory()->create([
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.courses.bulk'), [
                'ids' => [$course->id],
                'action' => 'status',
                'value' => PublishStatus::Published->value,
                'note' => 'Approved for launch.',
            ])
            ->assertRedirect();

        $course->refresh();

        $this->assertSame(PublishStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $course->getMorphClass(),
            'subject_id' => $course->id,
            'actor_id' => $this->admin->id,
            'from_status' => PublishStatus::Draft->value,
            'to_status' => PublishStatus::Published->value,
            'note' => 'Approved for launch.',
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'admin.courses.bulk_status',
            'auditable_id' => $course->id,
        ]);
    }

    public function test_admin_approval_of_blogger_records_history_and_notes(): void
    {
        $blogger = User::factory()->create();
        $blogger->assignRole(RoleName::Blogger->value);

        $profile = BloggerProfile::factory()->create([
            'user_id' => $blogger->id,
            'status' => BloggerStatus::Pending,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.bloggers.bulk'), [
                'ids' => [$profile->id],
                'action' => 'status',
                'value' => BloggerStatus::Approved->value,
                'note' => 'Portfolio reviewed.',
            ])
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(BloggerStatus::Approved, $profile->status);
        $this->assertSame($this->admin->id, $profile->reviewed_by);
        $this->assertSame('Portfolio reviewed.', $profile->admin_notes);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $profile->getMorphClass(),
            'subject_id' => $profile->id,
            'actor_id' => $this->admin->id,
            'from_status' => BloggerStatus::Pending->value,
            'to_status' => BloggerStatus::Approved->value,
            'note' => 'Portfolio reviewed.',
        ]);
    }
}
