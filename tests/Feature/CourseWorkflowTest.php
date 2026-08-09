<?php

namespace Tests\Feature;

use App\Enums\EditorialRevisionStatus;
use App\Enums\LearningResourceAccess;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\VideoType;
use App\Models\ApprovalHistory;
use App\Models\BloggerProfile;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CreatorAgreementAcceptance;
use App\Models\EditorialRevision;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creator_can_build_course_and_submit_with_ownership_proof(): void
    {
        $creator = $this->approvedCreator();
        $category = CourseCategory::factory()->create();
        $ownershipVideo = MediaAsset::factory()->create([
            'title' => 'Ownership confirmation video',
            'mime_type' => 'video/mp4',
            'path' => 'media/ownership-confirmation.mp4',
            'url' => '/storage/media/ownership-confirmation.mp4',
        ]);

        $this->actingAs($creator)
            ->post(route('creator.courses.store'), [
                'course_category_id' => $category->id,
                'title' => 'Creator course workflow',
                'short_description' => 'A practical course workflow.',
                'description' => 'Full course description for admin review.',
                'level' => 'intermediate',
                'language' => 'en',
                'price' => '49.00',
                'is_free' => '0',
            ])
            ->assertRedirect();

        $course = Course::query()->where('title', 'Creator course workflow')->firstOrFail();

        $this->actingAs($creator)
            ->patch(route('creator.courses.ownership.update', $course), [
                'ownership_video_media_id' => $ownershipVideo->id,
                'ownership_statement' => 'I confirm this course was created by me and I have rights to publish it on Scratch Learning.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($creator)
            ->post(route('creator.courses.sections.store', $course), [
                'title' => 'Operating model',
                'description' => 'Set up the review workflow.',
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $section = $course->sections()->where('title', 'Operating model')->firstOrFail();

        $this->actingAs($creator)
            ->post(route('creator.courses.lessons.store', $course), [
                'course_section_id' => $section->id,
                'title' => 'Build the first review queue',
                'order_number' => 1,
                'content' => 'Lesson body for the review queue.',
                'video_type' => VideoType::Url->value,
                'video_url' => 'https://videos.example.com/review-queue',
                'is_free' => '0',
                'is_paid' => '1',
                'preview_word_limit' => 120,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lesson = $course->lessons()->where('title', 'Build the first review queue')->firstOrFail();

        $this->actingAs($creator)
            ->post(route('creator.courses.resources.store', $course), [
                'course_lesson_id' => $lesson->id,
                'title' => 'Review checklist',
                'description' => 'Checklist PDF for learners.',
                'type' => 'download',
                'access_level' => LearningResourceAccess::Enrolled->value,
                'external_url' => 'https://resources.example.com/review-checklist',
                'is_downloadable' => '1',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($creator)
            ->post(route('creator.courses.faqs.store', $course), [
                'course_lesson_id' => $lesson->id,
                'question' => 'Can I reuse this checklist?',
                'answer' => 'Yes, adapt it to your team workflow.',
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($creator)
            ->get(route('creator.courses.edit', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/CourseBuilder')
                ->where('item.ownership_complete', true)
                ->has('builder.sections', 1)
                ->has('builder.lessons', 1)
                ->has('builder.resources', 1)
                ->has('builder.faqs', 1)
                ->where('builder.readiness_issues', []),
            );

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
        $this->assertDatabaseHas(CourseResource::class, [
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'title' => 'Review checklist',
        ]);
    }

    public function test_course_submission_requires_ownership_proof_and_curriculum(): void
    {
        $creator = $this->approvedCreator();
        $course = Course::factory()->withoutOwnershipProof()->create([
            'created_by' => $creator->id,
            'status' => PublishStatus::Draft,
        ]);

        $this->actingAs($creator)
            ->from(route('creator.courses.edit', $course, false))
            ->post(route('creator.courses.submit', $course), [
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasErrors('course')
            ->assertRedirect(route('creator.courses.edit', $course, false));

        $this->assertSame(PublishStatus::Draft, $course->fresh()->status);
    }

    public function test_admin_approval_publishes_course_and_children(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->pending()->create([
            'created_by' => $creator->id,
            'title' => 'Submitted course for approval',
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);
        $faq = CourseFaq::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->get(route('public.courses.show', $course->slug))->assertNotFound();

        $this->actingAs($admin)
            ->from('/admin/courses')
            ->post(route('admin.course-workflow.courses.approve', $course), [
                'note' => 'Ownership and curriculum reviewed.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/courses');

        $course->refresh();
        $section->refresh();
        $lesson->refresh();
        $faq->refresh();

        $this->assertSame(PublishStatus::Published, $course->status);
        $this->assertSame(PublishStatus::Published, $section->status);
        $this->assertSame(PublishStatus::Published, $lesson->status);
        $this->assertSame(PublishStatus::Published, $faq->status);
        $this->assertNotNull($course->published_at);

        $this->get(route('public.courses.show', $course->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/courses/Show')
                ->where('course.title', 'Submitted course for approval'),
            );
    }

    public function test_admin_course_table_exposes_course_workflow_actions(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->pending()->create([
            'created_by' => $creator->id,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/courses?status='.PublishStatus::Pending->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $course->id)
                ->where('rows.data.0.ownership', 'Complete')
                ->where('rows.data.0.workflow_actions.0.label', 'Approve')
                ->where('rows.data.0.workflow_actions.1.label', 'Changes')
                ->where('rows.data.0.workflow_actions.2.label', 'Reject'),
            );
    }

    public function test_generic_admin_publish_requires_course_approval_materials(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->withoutOwnershipProof()->create([
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        $this->actingAs($admin)
            ->from('/admin/courses')
            ->patch('/admin/courses/'.$course->id, [
                'title' => $course->title,
                'course_category_id' => $course->course_category_id,
                'course_subcategory_id' => $course->course_subcategory_id,
                'thumbnail_media_id' => $course->thumbnail_media_id,
                'short_description' => $course->short_description,
                'description' => $course->description,
                'level' => $course->level,
                'language' => $course->language,
                'price' => $course->price,
                'is_free' => false,
                'status' => PublishStatus::Published->value,
                'seo_title' => $course->seo_title,
                'seo_description' => $course->seo_description,
            ])
            ->assertStatus(422);

        $this->assertSame(PublishStatus::Draft, $course->fresh()->status);
    }

    public function test_published_course_main_edits_create_revision_without_changing_live_course(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->published()->create([
            'created_by' => $creator->id,
            'title' => 'Live course title',
            'description' => 'Original live course body.',
            'price' => 49,
        ]);
        $section = CourseSection::factory()->published()->create(['course_id' => $course->id]);
        CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        $this->actingAs($creator)
            ->get(route('creator.courses.edit', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/CourseBuilder')
                ->where('item.status', PublishStatus::Published->value),
            );

        $this->actingAs($creator)
            ->patch(route('creator.courses.update', $course), [
                'course_category_id' => $course->course_category_id,
                'course_subcategory_id' => $course->course_subcategory_id,
                'thumbnail_media_id' => $course->thumbnail_media_id,
                'title' => 'Revised course title',
                'short_description' => 'Updated summary.',
                'description' => 'Updated body awaiting review.',
                'intro_video_url' => 'https://videos.example.com/revised-course',
                'level' => 'advanced',
                'language' => 'en',
                'price' => '99.00',
                'is_free' => '0',
                'seo_title' => 'Revised course SEO',
                'seo_description' => 'Revised course SEO description.',
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $course->refresh();

        $this->assertSame('Live course title', $course->title);
        $this->assertSame('Original live course body.', $course->description);
        $this->assertSame('49.00', (string) $course->price);

        $revision = EditorialRevision::query()
            ->where('editorialable_type', $course->getMorphClass())
            ->where('editorialable_id', $course->id)
            ->firstOrFail();

        $this->assertSame(EditorialRevisionStatus::Submitted, $revision->status);
        $this->assertSame('Revised course title', $revision->payload['course']['title']);
        $this->assertSame('99.00', $revision->payload['course']['price']);

        $this->actingAs($admin)
            ->from('/admin/editorial/revisions')
            ->post(route('admin.course-workflow.revisions.approve', $revision), [
                'note' => 'Course revision approved.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/editorial/revisions');

        $course->refresh();
        $revision->refresh();

        $this->assertSame('Revised course title', $course->title);
        $this->assertSame('Updated body awaiting review.', $course->description);
        $this->assertSame('99.00', (string) $course->price);
        $this->assertSame(EditorialRevisionStatus::Approved, $revision->status);
    }

    public function test_published_course_structure_edits_wait_for_admin_revision_approval(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->published()->create([
            'created_by' => $creator->id,
        ]);
        $section = CourseSection::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Live section',
        ]);
        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'title' => 'Live lesson',
            'content' => 'Live lesson content.',
        ]);
        $resource = CourseResource::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'title' => 'Live resource',
        ]);

        $this->actingAs($creator)
            ->patch(route('creator.course-lessons.update', $lesson), [
                'course_section_id' => $section->id,
                'title' => 'Revised live lesson',
                'order_number' => 5,
                'content' => 'Revised lesson content.',
                'video_type' => VideoType::Url->value,
                'video_url' => 'https://videos.example.com/revised-live-lesson',
                'is_free' => '1',
                'is_paid' => '0',
                'preview_word_limit' => 0,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($creator)
            ->delete(route('creator.course-resources.destroy', $resource))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lesson->refresh();

        $this->assertSame('Live lesson', $lesson->title);
        $this->assertSame('Live lesson content.', $lesson->content);
        $this->assertDatabaseHas(CourseResource::class, [
            'id' => $resource->id,
            'title' => 'Live resource',
        ]);

        $revision = EditorialRevision::query()
            ->where('editorialable_type', $course->getMorphClass())
            ->where('editorialable_id', $course->id)
            ->firstOrFail();

        $this->assertSame(EditorialRevisionStatus::Submitted, $revision->status);
        $this->assertCount(2, $revision->payload['operations']);
        $this->assertSame('lessons', $revision->payload['operations'][0]['target']);
        $this->assertSame('resources', $revision->payload['operations'][1]['target']);

        $this->actingAs($admin)
            ->get('/admin/editorial/revisions?status='.EditorialRevisionStatus::Submitted->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $revision->id)
                ->where('rows.data.0.workflow_actions.0.url', route('admin.course-workflow.revisions.approve', $revision, false)),
            );

        $this->actingAs($admin)
            ->from('/admin/editorial/revisions')
            ->post(route('admin.course-workflow.revisions.approve', $revision), [
                'note' => 'Curriculum revision approved.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/editorial/revisions');

        $lesson->refresh();

        $this->assertSame('Revised live lesson', $lesson->title);
        $this->assertSame('Revised lesson content.', $lesson->content);
        $this->assertTrue($lesson->is_free);
        $this->assertFalse($lesson->is_paid);
        $this->assertDatabaseMissing(CourseResource::class, [
            'id' => $resource->id,
        ]);
    }

    public function test_rejected_course_revision_keeps_published_content_unchanged(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $course = Course::factory()->published()->create([
            'created_by' => $creator->id,
            'title' => 'Stable live course',
        ]);
        $section = CourseSection::factory()->published()->create(['course_id' => $course->id]);
        CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);

        $this->actingAs($creator)
            ->patch(route('creator.courses.update', $course), [
                'course_category_id' => $course->course_category_id,
                'course_subcategory_id' => $course->course_subcategory_id,
                'thumbnail_media_id' => $course->thumbnail_media_id,
                'title' => 'Rejected course title',
                'short_description' => $course->short_description,
                'description' => $course->description,
                'intro_video_url' => $course->intro_video_url,
                'level' => $course->level,
                'language' => $course->language,
                'price' => $course->price,
                'is_free' => $course->is_free ? '1' : '0',
                'seo_title' => $course->seo_title,
                'seo_description' => $course->seo_description,
                'copyright_declaration_accepted' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $revision = EditorialRevision::query()
            ->where('editorialable_type', $course->getMorphClass())
            ->where('editorialable_id', $course->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/editorial/revisions')
            ->post(route('admin.course-workflow.revisions.reject', $revision), [
                'note' => 'Keep current title.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/editorial/revisions');

        $course->refresh();
        $revision->refresh();

        $this->assertSame('Stable live course', $course->title);
        $this->assertSame(EditorialRevisionStatus::Rejected, $revision->status);
        $this->assertSame('Keep current title.', $revision->payload['review_note']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    private function approvedCreator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'bio' => 'Creator profile bio.',
            'expertise' => 'Laravel',
            'linkedin_url' => 'https://www.linkedin.com/in/course-creator',
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        return $user;
    }
}
