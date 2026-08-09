<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CreatorAgreementAcceptance;
use App\Models\CreatorContentDeletionRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteRequestTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creator_blog_delete_request_can_be_approved_to_trash_and_restored(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'title' => 'Article moving through trash',
        ]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), [
                'reason' => 'This article is outdated.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post->refresh();
        $this->assertSame(PublishStatus::DeleteRequested, $post->status);

        $this->actingAs($admin)
            ->get('/admin/blogs?status='.PublishStatus::DeleteRequested->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $post->id)
                ->where('rows.data.0.workflow_actions.0.label', 'Trash')
                ->where('rows.data.0.workflow_actions.1.label', 'Keep'),
            );

        $deletionRequest = CreatorContentDeletionRequest::query()
            ->where('content_type', $post->getMorphClass())
            ->where('content_id', $post->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->post(route('admin.blog-workflow.deletions.approve', $deletionRequest), [
                'note' => 'Approved removal.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/blogs');

        $trashedPost = BlogPost::withTrashed()->whereKey($post->id)->firstOrFail();

        $this->assertTrue($trashedPost->trashed());
        $this->assertSame(PublishStatus::Trashed, $trashedPost->status);
        $this->assertSame(CreatorContentDeletionStatus::Approved, $deletionRequest->fresh()->status);

        $this->actingAs($admin)
            ->get('/admin/trash?category=blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('resource', 'trash')
                ->where('canCreate', false)
                ->where('canEdit', false)
                ->where('canDelete', false)
                ->where('rows.data.0.id', 'blog:'.$post->id)
                ->where('rows.data.0.workflow_actions.0.label', 'Restore')
                ->where('rows.data.0.workflow_actions.1.label', 'Delete Forever')
                ->where('rows.data.0.workflow_actions.1.method', 'delete'),
            );

        $this->actingAs($admin)
            ->from('/admin/trash')
            ->post(route('admin.trash.restore', ['type' => 'blog', 'id' => $post->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/trash');

        $post->refresh();

        $this->assertFalse($post->trashed());
        $this->assertSame(PublishStatus::Published, $post->status);
    }

    public function test_creator_course_delete_request_can_be_rejected_and_kept_published(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->published()->create([
            'created_by' => $creator->id,
            'title' => 'Course kept after delete review',
        ]);

        $this->actingAs($creator)
            ->post(route('creator.courses.delete-request', $course), [
                'reason' => 'I want to replace it later.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $course->refresh();
        $this->assertSame(PublishStatus::DeleteRequested, $course->status);

        $this->actingAs($admin)
            ->get('/admin/courses?status='.PublishStatus::DeleteRequested->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $course->id)
                ->where('rows.data.0.workflow_actions.0.label', 'Trash')
                ->where('rows.data.0.workflow_actions.1.label', 'Keep'),
            );

        $deletionRequest = CreatorContentDeletionRequest::query()
            ->where('content_type', $course->getMorphClass())
            ->where('content_id', $course->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/courses')
            ->post(route('admin.course-workflow.deletions.reject', $deletionRequest), [
                'note' => 'Keep this course live.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/courses');

        $course->refresh();

        $this->assertFalse($course->trashed());
        $this->assertSame(PublishStatus::Published, $course->status);
        $this->assertSame(CreatorContentDeletionStatus::Rejected, $deletionRequest->fresh()->status);
    }

    public function test_admin_delete_moves_content_to_trash_and_permanent_delete_is_audited(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->published()->create([
            'title' => 'Course heading to permanent delete',
        ]);

        $this->actingAs($admin)
            ->from('/admin/courses')
            ->delete('/admin/courses/'.$course->id)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/courses');

        $trashedCourse = Course::withTrashed()->whereKey($course->id)->firstOrFail();

        $this->assertTrue($trashedCourse->trashed());
        $this->assertSame(PublishStatus::Trashed, $trashedCourse->status);

        $creator = $this->approvedCreator();

        $this->actingAs($creator)
            ->delete(route('admin.trash.force-delete', ['type' => 'course', 'id' => $course->id]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from('/admin/trash')
            ->delete(route('admin.trash.force-delete', ['type' => 'course', 'id' => $course->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/trash');

        $this->assertFalse(Course::withTrashed()->whereKey($course->id)->exists());
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_id' => $admin->id,
            'action' => 'admin.trash.force_deleted',
            'auditable_type' => (new Course)->getMorphClass(),
            'auditable_id' => $course->id,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    private function approvedCreator(): User
    {
        Role::findOrCreate(RoleName::Blogger->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'status' => BloggerStatus::Approved,
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        return $user;
    }
}
