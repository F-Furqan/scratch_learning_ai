<?php

namespace Tests\Feature;

use App\Enums\CopyrightTakedownStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\VideoType;
use App\Models\ApprovalHistory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CopyrightTakedownRequest;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\CreatorAgreementAcceptance;
use App\Models\EditorialRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreatorLegalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creator_guidelines_page_is_available_to_approved_creators(): void
    {
        $creator = $this->approvedCreator();

        $this->actingAs($creator)
            ->get(route('creator.guidelines'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/Guidelines')
                ->where('sections.0.title', 'Originality and rights')
                ->where('sections.2.title', 'Review workflow'),
            );
    }

    public function test_blog_submission_requires_and_records_copyright_declaration(): void
    {
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->create([
            'author_id' => $creator->id,
            'status' => PublishStatus::Draft,
        ]);

        $this->actingAs($creator)
            ->from(route('creator.blogs.edit', $post, false))
            ->post(route('creator.blogs.submit', $post))
            ->assertSessionHasErrors('copyright_declaration_accepted')
            ->assertRedirect(route('creator.blogs.edit', $post, false));

        $this->assertSame(PublishStatus::Draft, $post->fresh()->status);
        $this->assertNull($post->fresh()->copyright_declaration_accepted_at);

        $this->actingAs($creator)
            ->withHeader('User-Agent', 'Creator Legal Browser')
            ->post(route('creator.blogs.submit', $post), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Submitted->value], false));

        $post->refresh();

        $this->assertSame(PublishStatus::Submitted, $post->status);
        $this->assertNotNull($post->copyright_declaration_accepted_at);
        $this->assertSame('Creator Legal Browser', $post->copyright_declaration_user_agent);
    }

    public function test_course_submission_requires_and_records_copyright_declaration(): void
    {
        $creator = $this->approvedCreator();
        $course = $this->readyCourse($creator);

        $this->actingAs($creator)
            ->from(route('creator.courses.edit', $course, false))
            ->post(route('creator.courses.submit', $course))
            ->assertSessionHasErrors('copyright_declaration_accepted')
            ->assertRedirect(route('creator.courses.edit', $course, false));

        $this->assertSame(PublishStatus::Draft, $course->fresh()->status);
        $this->assertNull($course->fresh()->copyright_declaration_accepted_at);

        $this->actingAs($creator)
            ->withHeader('User-Agent', 'Course Legal Browser')
            ->post(route('creator.courses.submit', $course), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('creator.courses.index', ['status' => PublishStatus::Pending->value], false));

        $course->refresh();

        $this->assertSame(PublishStatus::Pending, $course->status);
        $this->assertNotNull($course->copyright_declaration_accepted_at);
        $this->assertSame('Course Legal Browser', $course->copyright_declaration_user_agent);
    }

    public function test_published_blog_revision_requires_declaration_and_keeps_live_content_locked(): void
    {
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'title' => 'Protected legal article',
            'content' => 'Live legal body.',
        ]);

        $payload = [
            'blog_category_id' => $post->blog_category_id,
            'title' => 'Changed legal article',
            'excerpt' => 'Changed excerpt.',
            'content' => 'Changed body.',
            'seo_title' => 'Changed SEO',
            'seo_description' => 'Changed SEO description.',
        ];

        $this->actingAs($creator)
            ->from(route('creator.blogs.edit', $post, false))
            ->patch(route('creator.blogs.update', $post), $payload)
            ->assertSessionHasErrors('copyright_declaration_accepted')
            ->assertRedirect(route('creator.blogs.edit', $post, false));

        $this->assertSame(0, EditorialRevision::query()->where('editorialable_id', $post->id)->count());

        $this->actingAs($creator)
            ->patch(route('creator.blogs.update', $post), [
                ...$payload,
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Published->value], false));

        $post->refresh();
        $revision = EditorialRevision::query()->where('editorialable_id', $post->id)->firstOrFail();

        $this->assertSame('Protected legal article', $post->title);
        $this->assertSame('Live legal body.', $post->content);
        $this->assertSame(EditorialRevisionStatus::Submitted, $revision->status);
        $this->assertSame('Changed legal article', $revision->payload['title']);
    }

    public function test_public_copyright_takedown_request_links_reported_content(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Reported public article',
        ]);

        $this->post(route('copyright.takedown.store'), [
            'claimant_name' => 'Rights Owner',
            'claimant_email' => 'rights@example.com',
            'claimant_company' => 'Rights Company',
            'rights_owner' => 'Rights Owner',
            'original_work_url' => 'https://rights.example.com/original',
            'infringing_url' => route('public.blog.show', $post->slug),
            'content_title' => 'Reported public article',
            'description' => 'This content uses my copyrighted work.',
            'good_faith_confirmed' => '1',
            'accuracy_confirmed' => '1',
            'signature' => 'Rights Owner',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $request = CopyrightTakedownRequest::query()->firstOrFail();

        $this->assertSame(CopyrightTakedownStatus::Submitted, $request->status);
        $this->assertSame($post->getMorphClass(), $request->reportable_type);
        $this->assertSame($post->id, $request->reportable_id);
    }

    public function test_admin_copyright_takedown_decision_records_checklist_history(): void
    {
        $admin = $this->admin();
        $post = BlogPost::factory()->published()->create();
        $takedownRequest = CopyrightTakedownRequest::factory()->create([
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
            'status' => CopyrightTakedownStatus::Submitted,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.review-center.index', absolute: false))
            ->post(route('admin.review-center.copyright-takedowns.resolve', $takedownRequest), [
                'note' => 'Verified rights claim and resolved.',
                'review_checklist' => [
                    'ownership_rights' => true,
                    'policy_safety' => true,
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.review-center.index', absolute: false));

        $takedownRequest->refresh();

        $this->assertSame(CopyrightTakedownStatus::Resolved, $takedownRequest->status);
        $this->assertSame($admin->id, $takedownRequest->reviewed_by);
        $this->assertNotNull($takedownRequest->reviewed_at);
        $this->assertSame('Verified rights claim and resolved.', $takedownRequest->resolution_note);

        $history = ApprovalHistory::query()
            ->where('subject_type', $takedownRequest->getMorphClass())
            ->where('subject_id', $takedownRequest->id)
            ->where('decision', 'copyright_takedown_resolved')
            ->firstOrFail();

        $this->assertTrue($history->metadata['review_checklist']['ownership_rights']);
        $this->assertTrue($history->metadata['review_checklist']['policy_safety']);
    }

    public function test_verified_creator_badge_data_is_available_on_public_profile(): void
    {
        $creator = $this->approvedCreator();

        $this->get(route('public.bloggers.show', $creator))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/bloggers/Show')
                ->where('blogger.is_verified_creator', true),
            );
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
            'bio' => 'Creator profile bio.',
            'expertise' => 'Laravel',
            'linkedin_url' => 'https://www.linkedin.com/in/legal-creator',
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        return $user;
    }

    private function readyCourse(User $creator): Course
    {
        $course = Course::factory()->create([
            'created_by' => $creator->id,
            'status' => PublishStatus::Draft,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'video_type' => VideoType::Url->value,
            'video_url' => 'https://videos.example.com/legal-course',
        ]);

        return $course;
    }
}
