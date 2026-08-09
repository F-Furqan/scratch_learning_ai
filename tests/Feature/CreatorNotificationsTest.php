<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\CreatorAgreementAcceptance;
use App\Models\CreatorContentDeletionRequest;
use App\Models\User;
use App\Notifications\CreatorWorkflowNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreatorNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_blog_workflow_creates_creator_dashboard_notifications(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();

        $submittedByCreator = BlogPost::factory()->create(['author_id' => $creator->id]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.submit', $submittedByCreator), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Submitted->value], false));

        $approved = BlogPost::factory()->submitted()->create(['author_id' => $creator->id]);
        $changes = BlogPost::factory()->submitted()->create(['author_id' => $creator->id]);
        $rejected = BlogPost::factory()->submitted()->create(['author_id' => $creator->id]);
        $deleteApproved = BlogPost::factory()->published()->create(['author_id' => $creator->id]);
        $deleteRejected = BlogPost::factory()->published()->create(['author_id' => $creator->id]);

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.blogs.approve', $approved), ['note' => 'Blog is ready.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.blogs.changes-requested', $changes), ['note' => 'Please add examples.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.blogs.reject', $rejected), ['note' => 'Topic is not suitable.'])
            ->assertRedirect();

        $approvedDeletion = $this->requestBlogDeletion($creator, $deleteApproved, 'Retire this article.');
        $rejectedDeletion = $this->requestBlogDeletion($creator, $deleteRejected, 'Replace later.');

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.deletions.approve', $approvedDeletion), ['note' => 'Deletion approved.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.blog-workflow.deletions.reject', $rejectedDeletion), ['note' => 'Keep it live.'])
            ->assertRedirect();

        $events = $this->notificationEvents($creator);

        $this->assertContains('blog_submitted', $events);
        $this->assertContains('blog_approved', $events);
        $this->assertContains('blog_changes_requested', $events);
        $this->assertContains('blog_rejected', $events);
        $this->assertContains('blog_delete_approved', $events);
        $this->assertContains('blog_delete_rejected', $events);
    }

    public function test_course_workflow_creates_creator_dashboard_notifications(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();

        $submittedByCreator = $this->readyCourse($creator, PublishStatus::Draft);

        $this->actingAs($creator)
            ->post(route('creator.courses.submit', $submittedByCreator), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertRedirect(route('creator.courses.index', ['status' => PublishStatus::Pending->value], false));

        $approved = $this->readyCourse($creator, PublishStatus::Pending);
        $changes = $this->readyCourse($creator, PublishStatus::Pending);
        $rejected = $this->readyCourse($creator, PublishStatus::Pending);
        $deleteApproved = Course::factory()->published()->create(['created_by' => $creator->id]);
        $deleteRejected = Course::factory()->published()->create(['created_by' => $creator->id]);

        $this->actingAs($admin)
            ->post(route('admin.course-workflow.courses.approve', $approved), ['note' => 'Course is ready.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.course-workflow.courses.changes-requested', $changes), ['note' => 'Improve lesson outline.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.course-workflow.courses.reject', $rejected), ['note' => 'Ownership proof is unclear.'])
            ->assertRedirect();

        $approvedDeletion = $this->requestCourseDeletion($creator, $deleteApproved, 'Retire this course.');
        $rejectedDeletion = $this->requestCourseDeletion($creator, $deleteRejected, 'Replace this course.');

        $this->actingAs($admin)
            ->post(route('admin.course-workflow.deletions.approve', $approvedDeletion), ['note' => 'Deletion approved.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.course-workflow.deletions.reject', $rejectedDeletion), ['note' => 'Keep it published.'])
            ->assertRedirect();

        $events = $this->notificationEvents($creator);

        $this->assertContains('course_submitted', $events);
        $this->assertContains('course_approved', $events);
        $this->assertContains('course_changes_requested', $events);
        $this->assertContains('course_rejected', $events);
        $this->assertContains('course_delete_approved', $events);
        $this->assertContains('course_delete_rejected', $events);
    }

    public function test_creator_dashboard_shows_and_clears_alerts(): void
    {
        $creator = $this->approvedCreator();

        $creator->notify(new CreatorWorkflowNotification([
            'event' => 'blog_approved',
            'severity' => 'success',
            'title' => 'Blog approved',
            'message' => 'Your blog was approved.',
            'note' => 'Strong practical examples.',
            'content_type' => 'blog',
            'content_id' => 10,
            'content_title' => 'Creator blog',
            'status' => PublishStatus::Approved->value,
            'action_url' => route('creator.blogs.index', absolute: false),
        ]));

        $notification = $this->notificationsFor($creator)->firstOrFail();

        $this->actingAs($creator)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/Dashboard')
                ->where('unread_notification_count', 1)
                ->where('notifications.0.title', 'Blog approved')
                ->where('notifications.0.read_at', null),
            );

        $this->actingAs($creator)
            ->patch(route('creator.notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);

        $creator->notify(new CreatorWorkflowNotification([
            'event' => 'course_rejected',
            'severity' => 'danger',
            'title' => 'Course rejected',
            'message' => 'Your course was rejected.',
            'content_type' => 'course',
            'content_id' => 11,
            'content_title' => 'Creator course',
            'status' => PublishStatus::Rejected->value,
            'action_url' => route('creator.courses.index', absolute: false),
        ]));

        $this->actingAs($creator)
            ->patch(route('creator.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->notificationsFor($creator)->whereNull('read_at')->count());
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

        BloggerProfile::factory()->create([
            'user_id' => $user->id,
            'status' => BloggerStatus::Approved,
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

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

    private function requestBlogDeletion(User $creator, BlogPost $post, string $reason): CreatorContentDeletionRequest
    {
        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), ['reason' => $reason])
            ->assertRedirect();

        return CreatorContentDeletionRequest::query()
            ->where('requester_id', $creator->id)
            ->where('content_type', $post->getMorphClass())
            ->where('content_id', $post->id)
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->firstOrFail();
    }

    private function requestCourseDeletion(User $creator, Course $course, string $reason): CreatorContentDeletionRequest
    {
        $this->actingAs($creator)
            ->post(route('creator.courses.delete-request', $course), ['reason' => $reason])
            ->assertRedirect();

        return CreatorContentDeletionRequest::query()
            ->where('requester_id', $creator->id)
            ->where('content_type', $course->getMorphClass())
            ->where('content_id', $course->id)
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function notificationEvents(User $creator): array
    {
        return $this->notificationsFor($creator)
            ->get()
            ->map(fn (DatabaseNotification $notification): string => (string) ($notification->data['event'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function notificationsFor(User $creator)
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $creator->getMorphClass())
            ->where('notifiable_id', $creator->id);
    }
}
