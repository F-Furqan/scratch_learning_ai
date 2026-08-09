<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\VideoType;
use App\Models\ApprovalHistory;
use App\Models\BlogCategory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CreatorAgreementAcceptance;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\CreatorContentDeletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreatorDashboardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_creator_dashboard_shows_profile_status_counts_requests_and_engagement(): void
    {
        $creator = $this->approvedCreator();

        BlogPost::factory()->create(['author_id' => $creator->id, 'status' => PublishStatus::Draft]);
        BlogPost::factory()->pending()->create(['author_id' => $creator->id]);
        BlogPost::factory()->published()->create(['author_id' => $creator->id]);
        BlogPost::factory()->create([
            'author_id' => $creator->id,
            'status' => PublishStatus::Rejected,
            'rejection_reason' => 'Add stronger examples.',
        ]);

        Course::factory()->create(['created_by' => $creator->id, 'status' => PublishStatus::Draft]);
        Course::factory()->pending()->create(['created_by' => $creator->id]);
        $publishedCourse = Course::factory()->published()->create(['created_by' => $creator->id]);
        Course::factory()->create([
            'created_by' => $creator->id,
            'status' => PublishStatus::Rejected,
            'rejection_reason' => 'Upload a clearer course outline.',
        ]);

        CreatorContentDeletionRequest::query()->create([
            'requester_id' => $creator->id,
            'content_type' => $publishedCourse->getMorphClass(),
            'content_id' => $publishedCourse->id,
            'status' => CreatorContentDeletionStatus::Pending,
            'reason' => 'Replacing this course.',
        ]);

        CreatorAnalyticsSnapshot::factory()->create([
            'user_id' => $creator->id,
            'blog_views' => 120,
            'course_views' => 340,
            'lesson_views' => 560,
            'comments_count' => 7,
            'enrollments_count' => 9,
            'engagement_score' => 42,
        ]);

        $this->actingAs($creator)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/Dashboard')
                ->where('profile.blogger_status', BloggerStatus::Approved->value)
                ->where('agreement.accepted', true)
                ->where('summary.drafts', 2)
                ->where('summary.pending_approval', 2)
                ->where('summary.published', 2)
                ->where('summary.changes_requested', 2)
                ->where('summary.delete_requests', 1)
                ->where('analytics.blog_views', 120)
                ->where('analytics.course_views', 340)
                ->where('analytics.lesson_views', 560)
                ->where('analytics.engagement_score', 42)
                ->has('recent_content')
                ->has('delete_requests', 1),
            );
    }

    public function test_creator_can_update_profile_details(): void
    {
        $creator = $this->approvedCreator();

        $this->actingAs($creator)
            ->patch(route('creator.profile.update'), [
                'name' => 'Updated Creator',
                'phone' => '+1 555 0101',
                'bio' => 'I teach Laravel teams how to ship better.',
                'expertise' => 'Laravel architecture',
                'linkedin_url' => 'https://www.linkedin.com/in/updated-creator',
                'website_url' => 'https://creator.example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(User::class, [
            'id' => $creator->id,
            'name' => 'Updated Creator',
        ]);
        $this->assertDatabaseHas(BloggerProfile::class, [
            'user_id' => $creator->id,
            'bio' => 'I teach Laravel teams how to ship better.',
            'expertise' => 'Laravel architecture',
            'linkedin_url' => 'https://www.linkedin.com/in/updated-creator',
        ]);
    }

    public function test_creator_can_create_and_submit_blog_for_approval(): void
    {
        $creator = $this->approvedCreator();
        $category = BlogCategory::factory()->create();

        $this->actingAs($creator)
            ->post(route('creator.blogs.store'), [
                'blog_category_id' => $category->id,
                'title' => 'Creator editorial operating model',
                'excerpt' => 'A short summary.',
                'content' => 'A detailed creator blog draft.',
            ])
            ->assertRedirect();

        $post = BlogPost::query()->where('title', 'Creator editorial operating model')->firstOrFail();

        $this->assertSame($creator->id, $post->author_id);
        $this->assertSame(PublishStatus::Draft, $post->status);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $post->getMorphClass(),
            'subject_id' => $post->id,
            'decision' => 'draft_created',
        ]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.submit', $post), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Submitted->value], false));

        $this->assertSame(PublishStatus::Submitted, $post->fresh()->status);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $post->getMorphClass(),
            'subject_id' => $post->id,
            'decision' => 'submitted',
            'to_status' => PublishStatus::Submitted->value,
        ]);
    }

    public function test_creator_can_create_and_submit_course_for_approval(): void
    {
        $creator = $this->approvedCreator();
        $category = CourseCategory::factory()->create();

        $this->actingAs($creator)
            ->post(route('creator.courses.store'), [
                'course_category_id' => $category->id,
                'title' => 'Industrial creator course',
                'short_description' => 'A practical course.',
                'description' => 'Full course description for review.',
                'level' => 'intermediate',
                'language' => 'en',
                'price' => '49.00',
                'is_free' => '0',
            ])
            ->assertRedirect();

        $course = Course::query()->where('title', 'Industrial creator course')->firstOrFail();

        $this->assertSame($creator->id, $course->created_by);
        $this->assertSame(PublishStatus::Draft, $course->status);

        $this->actingAs($creator)
            ->patch(route('creator.courses.ownership.update', $course), [
                'ownership_video_url' => 'https://videos.example.com/creator-ownership-confirmation',
                'ownership_statement' => 'I confirm this course was created by me and I have rights to publish it on Scratch Learning.',
            ])
            ->assertRedirect();

        $this->actingAs($creator)
            ->post(route('creator.courses.sections.store', $course), [
                'title' => 'Course foundation',
                'description' => 'Core operating model.',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $section = $course->sections()->firstOrFail();

        $this->actingAs($creator)
            ->post(route('creator.courses.lessons.store', $course), [
                'course_section_id' => $section->id,
                'title' => 'Foundation lesson',
                'order_number' => 1,
                'content' => 'Lesson content for approval.',
                'video_type' => VideoType::Url->value,
                'video_url' => 'https://videos.example.com/foundation-lesson',
                'is_free' => '0',
                'is_paid' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($creator)
            ->post(route('creator.courses.submit', $course), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertRedirect(route('creator.courses.index', ['status' => PublishStatus::Pending->value], false));

        $this->assertSame(PublishStatus::Pending, $course->fresh()->status);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $course->getMorphClass(),
            'subject_id' => $course->id,
            'decision' => 'submitted',
            'to_status' => PublishStatus::Pending->value,
        ]);
    }

    public function test_creator_content_routes_require_current_agreement(): void
    {
        $creator = $this->approvedCreator(accepted: false);

        $this->actingAs($creator)
            ->get(route('creator.blogs.index'))
            ->assertRedirect(route('creator.agreement.show', absolute: false));
    }

    public function test_creator_can_request_deletion_without_duplicates(): void
    {
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->published()->create(['author_id' => $creator->id]);

        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), [
                'reason' => 'This content is outdated.',
            ])
            ->assertRedirect();

        $this->actingAs($creator)
            ->post(route('creator.blogs.delete-request', $post), [
                'reason' => 'Duplicate request.',
            ])
            ->assertRedirect();

        $this->assertSame(1, CreatorContentDeletionRequest::query()
            ->where('requester_id', $creator->id)
            ->where('content_type', $post->getMorphClass())
            ->where('content_id', $post->id)
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->count());
    }

    private function approvedCreator(bool $accepted = true): User
    {
        Role::findOrCreate(RoleName::Blogger->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'bio' => 'Creator profile bio.',
            'expertise' => 'Laravel',
            'linkedin_url' => 'https://www.linkedin.com/in/creator-profile',
        ]);

        if ($accepted) {
            CreatorAgreementAcceptance::factory()->create([
                'user_id' => $user->id,
                'terms_version' => config('platform.creator_agreement.version'),
            ]);
        }

        return $user;
    }
}
