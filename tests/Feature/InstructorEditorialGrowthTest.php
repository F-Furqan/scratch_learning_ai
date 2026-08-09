<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\InstructorProfileStatus;
use App\Enums\PublishStatus;
use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use App\Enums\RoleName;
use App\Enums\ScheduledPublicationStatus;
use App\Models\AuthorBadge;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\EditorialRevision;
use App\Models\InstructorProfile;
use App\Models\RevenueShareRule;
use App\Models\ReviewerComment;
use App\Models\User;
use App\Services\Creators\EditorialWorkflowService;
use App\Services\Creators\RevenueShareService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstructorEditorialGrowthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_public_instructor_profile_shows_badges_and_verified_expert_label(): void
    {
        $instructor = User::factory()->create(['name' => 'Dr Platform']);
        $profile = InstructorProfile::factory()->verified()->create([
            'user_id' => $instructor->id,
            'display_name' => 'Dr Platform',
            'expertise' => 'Laravel Architecture',
        ]);
        $badge = AuthorBadge::factory()->verifiedExpert()->create();
        $badge->users()->attach($instructor->id, [
            'awarded_by' => $this->admin->id,
            'awarded_at' => now(),
        ]);
        Course::factory()->published()->create([
            'created_by' => $instructor->id,
            'title' => 'Editorial Laravel',
        ]);
        BlogPost::factory()->published()->create([
            'author_id' => $instructor->id,
            'title' => 'Editorial Growth',
        ]);

        $this->get(route('public.instructors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('instructors.data.0.display_name', 'Dr Platform')
                ->where('instructors.data.0.is_verified_expert', true)
                ->where('instructors.data.0.badges.0.name', 'Verified Expert'),
            );

        $this->get(route('public.instructors.show', $profile->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('instructor.display_name', 'Dr Platform')
                ->where('instructor.course_count', 1)
                ->where('instructor.post_count', 1),
            );
    }

    public function test_creator_dashboard_includes_analytics_revisions_badges_and_revenue_rules(): void
    {
        $creator = User::factory()->create();
        $creator->assignRole(RoleName::Blogger->value);
        BloggerProfile::factory()->create([
            'user_id' => $creator->id,
            'status' => BloggerStatus::Approved,
        ]);
        $profile = InstructorProfile::factory()->verified()->create([
            'user_id' => $creator->id,
            'display_name' => 'Creator One',
        ]);
        $badge = AuthorBadge::factory()->verifiedExpert()->create();
        $badge->users()->attach($creator->id, ['awarded_at' => now()]);
        CreatorAnalyticsSnapshot::factory()->forInstructor($profile)->create([
            'blog_views' => 500,
            'course_revenue_cents' => 25000,
            'engagement_score' => 88,
        ]);
        $revision = EditorialRevision::factory()->submitted()->create([
            'author_id' => $creator->id,
            'title' => 'Reviewable draft',
        ]);
        ReviewerComment::factory()->create([
            'editorial_revision_id' => $revision->id,
            'body' => 'Tighten the conclusion.',
        ]);
        $course = Course::factory()->published()->create(['created_by' => $creator->id]);
        RevenueShareRule::factory()->forInstructor($profile)->create([
            'course_id' => $course->id,
            'share_percent' => 40,
        ]);

        $this->actingAs($creator)
            ->get(route('creator.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.is_verified_expert', true)
                ->where('analytics.blog_views', 500)
                ->where('analytics.course_revenue_cents', 25000)
                ->where('revisions.0.title', 'Reviewable draft')
                ->where('revenue_rules.0.share_percent', 40),
            );
    }

    public function test_editorial_revision_comments_and_scheduled_publishing_workflow(): void
    {
        $author = User::factory()->create();
        $post = BlogPost::factory()->create([
            'author_id' => $author->id,
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'title' => 'Original title',
        ]);
        $editorial = app(EditorialWorkflowService::class);

        $revision = $editorial->createRevision($post, $author, [
            'title' => 'Published title',
            'content' => 'Published content.',
        ], 'Launch revision');
        $editorial->submitForReview($revision);
        $comment = $editorial->requestChanges($revision, $this->admin, 'Add a clearer intro.', 'content');
        $editorial->approve($revision->fresh(), $this->admin);
        $publication = $editorial->schedule($post, $author, now()->subMinute(), $revision->fresh(), $this->admin);

        $this->assertSame(1, $editorial->publishDue());

        $post->refresh();
        $revision->refresh();
        $publication->refresh();

        $this->assertSame('Published title', $post->title);
        $this->assertSame(PublishStatus::Published, $post->status);
        $this->assertSame(EditorialRevisionStatus::Published, $revision->status);
        $this->assertSame(ScheduledPublicationStatus::Published, $publication->status);
        $this->assertDatabaseHas(ReviewerComment::class, [
            'id' => $comment->id,
            'body' => 'Add a clearer intro.',
            'field_path' => 'content',
        ]);
    }

    public function test_revenue_share_service_selects_specific_rule_and_calculates_payout(): void
    {
        $instructor = User::factory()->create();
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $rule = RevenueShareRule::factory()->create([
            'user_id' => $instructor->id,
            'course_id' => $course->id,
            'type' => RevenueShareRuleType::Course,
            'status' => RevenueShareRuleStatus::Active,
            'share_percent' => 35,
            'fixed_amount_cents' => 500,
        ]);

        $service = app(RevenueShareService::class);

        $this->assertTrue($rule->is($service->activeRuleForCourse($course, $instructor)));
        $this->assertSame(4000, $service->calculateShareCents(10000, $rule));
    }

    public function test_admin_can_manage_instructor_profiles_and_author_badges(): void
    {
        $creator = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.creators.instructors.store'), [
                'user_id' => $creator->id,
                'display_name' => 'Industrial Instructor',
                'headline' => 'Teaches production systems.',
                'expertise' => 'Industrial Laravel',
                'status' => InstructorProfileStatus::Approved->value,
                'is_verified_expert' => true,
                'accepts_revenue_share' => true,
                'payout_currency' => 'USD',
            ])
            ->assertRedirect();

        $profile = InstructorProfile::query()->where('user_id', $creator->id)->firstOrFail();

        $this->assertSame(InstructorProfileStatus::Approved, $profile->status);
        $this->assertTrue($profile->is_verified_expert);

        $this->actingAs($this->admin)
            ->post(route('admin.creators.badges.store'), [
                'name' => 'Verified Expert',
                'description' => 'Reviewed expert.',
                'icon' => 'badge-check',
                'color' => 'teal',
                'marks_verified_expert' => true,
                'is_active' => true,
                'sort_order' => 1,
                'user_ids' => [$creator->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(AuthorBadge::class, [
            'name' => 'Verified Expert',
            'marks_verified_expert' => true,
        ]);
        $this->assertDatabaseHas('author_badge_user', [
            'user_id' => $creator->id,
        ]);
    }
}
