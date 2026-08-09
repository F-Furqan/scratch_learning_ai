<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\BlogCategory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\CreatorAgreementAcceptance;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase10TestingHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_registration_login_and_dashboard_redirect_are_hardened(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Phase Ten Student',
            'email' => 'phase-ten-student@example.com',
            'account_type' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'phase-ten-student@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($user->hasRole(RoleName::Student->value));
        $this->assertDatabaseHas(StudentProfile::class, ['user_id' => $user->id]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard', absolute: false));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->post(route('login.store'), [
            'email' => 'phase-ten-student@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_creator_registration_login_and_dashboard_redirect_are_hardened(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this
            ->withHeader('User-Agent', 'Phase Ten Creator Browser')
            ->post(route('register.store'), [
                'name' => 'Phase Ten Creator',
                'email' => 'phase-ten-creator@example.com',
                'account_type' => 'creator',
                'expertise' => 'Laravel curriculum',
                'linkedin_url' => 'https://www.linkedin.com/in/phase-ten-creator',
                'website_url' => 'https://creator.example.com',
                'application_reason' => 'I want to contribute blogs and courses.',
                'creator_agreement_accepted' => '1',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $user = User::query()->where('email', 'phase-ten-creator@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($user->hasRole(RoleName::Blogger->value));
        $this->assertFalse($user->hasRole(RoleName::Student->value));
        $this->assertDatabaseHas(BloggerProfile::class, [
            'user_id' => $user->id,
            'status' => BloggerStatus::Pending->value,
            'linkedin_url' => 'https://www.linkedin.com/in/phase-ten-creator',
        ]);
        $this->assertDatabaseHas(CreatorAgreementAcceptance::class, [
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
            'user_agent' => 'Phase Ten Creator Browser',
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('creator.dashboard', absolute: false));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->post(route('login.store'), [
            'email' => 'phase-ten-creator@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))
            ->assertRedirect(route('creator.dashboard', absolute: false));
    }

    public function test_creator_cannot_access_admin_or_publish_directly(): void
    {
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->submitted()->create(['author_id' => $creator->id]);

        $this->actingAs($creator)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->assertTrue(Gate::forUser($creator)->denies('publish', $post));

        $this->actingAs($creator)
            ->post(route('admin.blog-workflow.blogs.publish', $post), [
                'note' => 'Creator should not publish directly.',
            ])
            ->assertForbidden();

        $this->assertSame(PublishStatus::Submitted, $post->fresh()->status);
        $this->assertNull($post->fresh()->published_at);
    }

    public function test_creator_live_published_blog_edit_creates_revision_without_mutating_live_content(): void
    {
        $creator = $this->approvedCreator();
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'title' => 'Live protected blog',
            'content' => 'Live body stays stable.',
        ]);

        $this->assertTrue(Gate::forUser($creator)->denies('update', $post));
        $this->assertTrue(Gate::forUser($creator)->allows('requestRevision', $post));

        $this->actingAs($creator)
            ->patch(route('creator.blogs.update', $post), [
                'blog_category_id' => $category->id,
                'title' => 'Revision title',
                'excerpt' => 'Revision excerpt.',
                'content' => 'Revision body waits for admin.',
                'seo_title' => 'Revision SEO',
                'seo_description' => 'Revision SEO description.',
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Published->value], false));

        $post->refresh();

        $this->assertSame('Live protected blog', $post->title);
        $this->assertSame('Live body stays stable.', $post->content);

        $revision = EditorialRevision::query()
            ->where('editorialable_type', $post->getMorphClass())
            ->where('editorialable_id', $post->id)
            ->firstOrFail();

        $this->assertSame(EditorialRevisionStatus::Submitted, $revision->status);
        $this->assertSame('Revision title', $revision->payload['title']);
    }

    public function test_admin_approval_publishes_course_content(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = $this->readyCourse($creator, PublishStatus::Pending);

        $this->get(route('public.courses.show', $course->slug))->assertNotFound();

        $this->actingAs($admin)
            ->from('/admin/courses')
            ->post(route('admin.course-workflow.courses.approve', $course), [
                'note' => 'Ownership and lessons reviewed.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/courses');

        $course->refresh();

        $this->assertSame(PublishStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);

        $this->get(route('public.courses.show', $course->slug))->assertOk();
    }

    public function test_rejected_content_stays_hidden_from_public_pages(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->submitted()->create([
            'author_id' => $creator->id,
            'title' => 'Rejected hidden blog',
        ]);

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->post(route('admin.blog-workflow.blogs.reject', $post), [
                'note' => 'Not aligned with editorial standards.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/blogs');

        $this->assertSame(PublishStatus::Rejected, $post->fresh()->status);
        $this->get(route('public.blog.show', $post->slug))->assertNotFound();
    }

    public function test_delete_request_soft_deletes_only_after_admin_approval(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->published()->create(['author_id' => $creator->id]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), [
                'reason' => 'Outdated content.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post->refresh();
        $this->assertFalse($post->trashed());
        $this->assertSame(PublishStatus::DeleteRequested, $post->status);

        $deletionRequest = $this->deletionRequestFor($post);

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->post(route('admin.blog-workflow.deletions.approve', $deletionRequest), [
                'note' => 'Deletion approved.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/blogs');

        $trashedPost = BlogPost::withTrashed()->whereKey($post->id)->firstOrFail();

        $this->assertTrue($trashedPost->trashed());
        $this->assertSame(PublishStatus::Trashed, $trashedPost->status);
        $this->assertSame(CreatorContentDeletionStatus::Approved, $deletionRequest->fresh()->status);
    }

    public function test_admin_can_restore_and_permanently_delete_trashed_content(): void
    {
        $admin = $this->admin();
        $restorePost = $this->trashedBlogFor($admin);
        $deletePost = $this->trashedBlogFor($admin);

        $this->actingAs($admin)
            ->from('/admin/trash')
            ->post(route('admin.trash.restore', ['type' => 'blog', 'id' => $restorePost->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/trash');

        $restorePost->refresh();

        $this->assertFalse($restorePost->trashed());
        $this->assertSame(PublishStatus::Published, $restorePost->status);

        $this->actingAs($admin)
            ->from('/admin/trash')
            ->delete(route('admin.trash.force-delete', ['type' => 'blog', 'id' => $deletePost->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/trash');

        $this->assertFalse(BlogPost::withTrashed()->whereKey($deletePost->id)->exists());
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_id' => $admin->id,
            'action' => 'admin.trash.force_deleted',
            'auditable_type' => (new BlogPost)->getMorphClass(),
            'auditable_id' => $deletePost->id,
        ]);
    }

    public function test_terms_acceptance_is_required_before_creator_upload(): void
    {
        $creator = $this->approvedCreator(acceptAgreement: false);
        $category = BlogCategory::factory()->create();

        $this->actingAs($creator)
            ->post(route('creator.blogs.store'), [
                'blog_category_id' => $category->id,
                'title' => 'Blocked upload without agreement',
                'excerpt' => 'This should not be created.',
                'content' => 'Creator must accept terms before upload.',
            ])
            ->assertRedirect(route('creator.agreement.show', absolute: false));

        $this->assertDatabaseMissing(BlogPost::class, [
            'title' => 'Blocked upload without agreement',
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $creator->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.store'), [
                'blog_category_id' => $category->id,
                'title' => 'Allowed upload with agreement',
                'excerpt' => 'This draft can be created.',
                'content' => 'Agreement accepted before upload.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(BlogPost::class, [
            'author_id' => $creator->id,
            'title' => 'Allowed upload with agreement',
            'status' => PublishStatus::Draft->value,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    private function approvedCreator(bool $acceptAgreement = true): User
    {
        Role::findOrCreate(RoleName::Blogger->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'status' => BloggerStatus::Approved,
        ]);

        if ($acceptAgreement) {
            CreatorAgreementAcceptance::factory()->create([
                'user_id' => $user->id,
                'terms_version' => config('platform.creator_agreement.version'),
            ]);
        }

        return $user;
    }

    private function readyCourse(User $creator, PublishStatus $status): Course
    {
        $course = Course::factory()->create([
            'created_by' => $creator->id,
            'status' => $status,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        return $course;
    }

    private function deletionRequestFor(BlogPost $post): CreatorContentDeletionRequest
    {
        return CreatorContentDeletionRequest::query()
            ->where('content_type', $post->getMorphClass())
            ->where('content_id', $post->id)
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->firstOrFail();
    }

    private function trashedBlogFor(User $admin): BlogPost
    {
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->published()->create(['author_id' => $creator->id]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), [
                'reason' => 'Prepare trash state.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.deletions.approve', $this->deletionRequestFor($post)), [
                'note' => 'Approved for hardening setup.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return BlogPost::withTrashed()->whereKey($post->id)->firstOrFail();
    }
}
