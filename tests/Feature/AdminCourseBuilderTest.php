<?php

namespace Tests\Feature;

use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\VideoType;
use App\Models\ApprovalHistory;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\BloggerProfile;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\PublicSite\PublicContentPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCourseBuilderTest extends TestCase
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

    public function test_builder_requires_course_management_permission_and_renders_the_complete_workspace(): void
    {
        $course = Course::factory()->create();
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleName::SubAdmin->value);
        $viewer->givePermissionTo('admin.courses.view');

        $this->actingAs($viewer)
            ->get(route('admin.courses.builder.show', $course))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.courses.builder.show', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/Builder')
                ->where('course.id', $course->id)
                ->hasAll([
                    'sections',
                    'lessons',
                    'resources',
                    'faqs',
                    'quizzes',
                    'assignments',
                    'pricing',
                    'approval_history',
                    'readiness_issues',
                    'options.categories',
                    'options.media',
                    'urls.items',
                ]),
            );
    }

    public function test_course_table_links_to_builder_and_builder_enforces_category_pairing(): void
    {
        $course = Course::factory()->create();
        $category = CourseCategory::factory()->create();
        $otherCategory = CourseCategory::factory()->create();
        $subcategory = CourseSubcategory::factory()->for($otherCategory, 'category')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.workflow_actions.0.label', 'Builder')
                ->where('rows.data.0.workflow_actions.0.method', 'get'),
            );

        $this->actingAs($this->admin)
            ->from(route('admin.courses.builder.show', $course))
            ->patch(route('admin.courses.builder.update', $course), $this->coursePayload($course, [
                'course_category_id' => $category->id,
                'course_subcategory_id' => $subcategory->id,
            ]))
            ->assertSessionHasErrors('course_subcategory_id');

        $this->assertNotSame($category->id, $course->fresh()->course_category_id);
    }

    public function test_lesson_rich_text_is_sanitized_and_nested_items_cannot_cross_courses(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        $section = CourseSection::factory()->for($course)->create();
        $otherLesson = CourseLesson::factory()->for($other)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.courses.builder.items.store', [$course, 'kind' => 'lessons']), [
                'course_section_id' => $section->id,
                'title' => 'Safe rich lesson',
                'order_number' => 1,
                'content' => '<h2>Example</h2><script>alert(1)</script><pre><code class="language-php">echo "safe";</code></pre><a href="javascript:alert(2)">bad</a>',
                'video_type' => VideoType::None->value,
                'video_url' => null,
                'video_file_id' => null,
                'is_free' => true,
                'is_paid' => false,
                'preview_word_limit' => 20,
                'status' => PublishStatus::Draft->value,
                'seo_title' => null,
                'seo_description' => null,
            ])
            ->assertRedirect();

        $lesson = CourseLesson::query()->where('title', 'Safe rich lesson')->firstOrFail();
        $this->assertStringContainsString('<pre><code class="language-php">', $lesson->content);
        $this->assertStringNotContainsString('<script', $lesson->content);
        $this->assertStringNotContainsString('javascript:', $lesson->content);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'admin.course_builder.lessons_created']);

        $publicLesson = app(PublicContentPresenter::class)->lessonDetail($lesson, $course);
        $this->assertStringContainsString('<pre><code class="language-php">', (string) $publicLesson['content']);

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.items.update', [$course, 'kind' => 'lessons', 'id' => $otherLesson->id]), [
                'course_section_id' => $section->id,
                'title' => 'Cross-course mutation',
                'order_number' => 1,
                'content' => '<p>Blocked</p>',
                'video_type' => VideoType::None->value,
                'is_free' => true,
                'is_paid' => false,
                'status' => PublishStatus::Draft->value,
            ])
            ->assertNotFound();
    }

    public function test_builder_manages_resources_faqs_quizzes_questions_and_assignments(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();

        $this->actingAs($this->admin)->post($this->storeUrl($course, 'resources'), [
            'course_lesson_id' => $lesson->id,
            'title' => 'Deployment checklist',
            'description' => 'Protected resource.',
            'type' => 'pdf',
            'access_level' => 'enrolled',
            'external_url' => 'https://example.test/checklist.pdf',
            'is_downloadable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post($this->storeUrl($course, 'faqs'), [
            'course_lesson_id' => $lesson->id,
            'question' => 'What is required?',
            'answer' => 'A supported Laravel environment.',
            'sort_order' => 1,
            'status' => PublishStatus::Draft->value,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post($this->storeUrl($course, 'quizzes'), [
            'course_lesson_id' => $lesson->id,
            'title' => 'Architecture review',
            'description' => 'Validate the lesson.',
            'pass_score' => 75,
            'max_attempts' => 3,
            'time_limit_minutes' => 20,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();
        $quiz = Quiz::query()->where('title', 'Architecture review')->firstOrFail();

        $this->actingAs($this->admin)->post($this->storeUrl($course, 'quiz-questions'), [
            'quiz_id' => $quiz->id,
            'question' => 'Which layer owns authorization?',
            'type' => 'multiple_choice',
            'points' => 2,
            'options_json' => '["Policy","View"]',
            'correct_answer_json' => '"Policy"',
            'explanation' => 'Policies own resource authorization.',
            'sort_order' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post($this->storeUrl($course, 'assignments'), [
            'course_lesson_id' => $lesson->id,
            'title' => 'Build a policy',
            'instructions' => '<p>Submit code.</p><script>unsafe()</script>',
            'pass_score' => 70,
            'max_points' => 100,
            'due_days_after_enrollment' => 7,
            'allow_file_uploads' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas(CourseResource::class, ['course_id' => $course->id, 'title' => 'Deployment checklist']);
        $this->assertDatabaseHas(CourseFaq::class, ['course_id' => $course->id, 'question' => 'What is required?']);
        $this->assertDatabaseHas(QuizQuestion::class, ['quiz_id' => $quiz->id, 'points' => 2]);
        $assignment = Assignment::query()->where('title', 'Build a policy')->firstOrFail();
        $this->assertStringContainsString('<p>Submit code.</p>', $assignment->instructions);
        $this->assertStringNotContainsString('<script', $assignment->instructions);
    }

    public function test_curriculum_reordering_is_scoped_and_audited(): void
    {
        $course = Course::factory()->create();
        $first = CourseSection::factory()->for($course)->create(['sort_order' => 0]);
        $second = CourseSection::factory()->for($course)->create(['sort_order' => 1]);

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.reorder', $course), [
                'type' => 'sections',
                'ids' => [$second->id, $first->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(2, AuditLog::query()->where('action', 'admin.course_builder.sections_reordered')->count());

        $foreign = CourseSection::factory()->create();
        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.reorder', $course), [
                'type' => 'sections',
                'ids' => [$first->id, $foreign->id],
            ])
            ->assertSessionHasErrors('ids');
    }

    public function test_builder_creates_a_paddle_ready_product_and_price_plan(): void
    {
        $course = Course::factory()->create(['is_free' => true, 'price' => 0]);

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.product', $course), [
                'is_free' => false,
                'price' => 49.99,
                'name' => 'Professional course access',
                'description' => 'Single-course access.',
                'type' => PaymentProductType::Course->value,
                'status' => PaymentProductStatus::Active->value,
                'paddle_product_id' => 'pro_builder_course',
                'tax_category' => 'standard',
            ])
            ->assertRedirect();

        $product = PaymentProduct::query()->where('course_id', $course->id)->firstOrFail();
        $this->actingAs($this->admin)
            ->post($this->storeUrl($course, 'prices'), [
                'name' => 'Lifetime access',
                'paddle_price_id' => 'pri_builder_lifetime',
                'billing_interval' => PaymentBillingInterval::OneTime->value,
                'is_recurring' => false,
                'currency' => 'usd',
                'amount' => 4999,
                'trial_days' => null,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(PaymentPrice::class, [
            'payment_product_id' => $product->id,
            'currency' => 'USD',
            'amount' => 4999,
        ]);
        $this->assertFalse($course->fresh()->is_free);
    }

    public function test_ownership_and_publishing_are_validated_recorded_and_cascade_to_curriculum(): void
    {
        $creator = User::factory()->create();
        BloggerProfile::factory()->approved()->for($creator)->create();
        $course = Course::factory()->for($creator, 'creator')->create([
            'description' => 'A complete course ready for approval.',
            'status' => PublishStatus::Pending,
        ]);
        $section = CourseSection::factory()->for($course)->create(['status' => PublishStatus::Draft]);
        $lesson = CourseLesson::factory()->for($course)->for($section, 'section')->create(['status' => PublishStatus::Draft]);

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.publishing', $course), [
                'status' => PublishStatus::Trashed->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(PublishStatus::Pending, $course->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.ownership', $course), [
                'ownership_video_url' => 'https://example.test/ownership/video',
                'ownership_statement' => 'The creator confirms ownership and publication rights.',
                'confirmed' => true,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.builder.publishing', $course), [
                'status' => PublishStatus::Published->value,
                'admin_notes' => 'Reviewed in the complete builder.',
            ])
            ->assertRedirect();

        $this->assertSame(PublishStatus::Published, $course->fresh()->status);
        $this->assertSame(PublishStatus::Published, $section->fresh()->status);
        $this->assertSame(PublishStatus::Published, $lesson->fresh()->status);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => Course::class,
            'subject_id' => $course->id,
            'decision' => 'approved',
            'to_status' => PublishStatus::Published->value,
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function coursePayload(Course $course, array $overrides = []): array
    {
        return [
            'title' => $course->title,
            'course_category_id' => $course->course_category_id,
            'course_subcategory_id' => $course->course_subcategory_id,
            'created_by' => $course->created_by,
            'thumbnail_media_id' => $course->thumbnail_media_id,
            'short_description' => $course->short_description,
            'description' => $course->description,
            'intro_video_url' => $course->intro_video_url,
            'level' => $course->level,
            'language' => $course->language ?: 'en',
            'seo_title' => $course->seo_title,
            'seo_description' => $course->seo_description,
            'seo_image' => $course->seo_image,
            'canonical_url' => $course->canonical_url,
            'schema' => null,
            ...$overrides,
        ];
    }

    private function storeUrl(Course $course, string $kind): string
    {
        return route('admin.courses.builder.items.store', [$course, 'kind' => $kind]);
    }
}
