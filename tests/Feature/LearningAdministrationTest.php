<?php

namespace Tests\Feature;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\DripReleaseType;
use App\Enums\LearningCatalogStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LearningPath;
use App\Models\LessonBookmark;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Registry\AdminResourceRegistry;
use Tests\TestCase;

class LearningAdministrationTest extends TestCase
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

    public function test_phase_five_resources_are_registered_with_safe_capabilities(): void
    {
        [$course, $lesson] = $this->courseAndLesson();
        $resources = [
            'course_enrollments',
            'learning_paths',
            'course_bundles',
            'skill_tracks',
            'drip_schedules',
            'quizzes',
            'quiz_questions',
            'quiz_attempts',
            'assignments',
            'assignment_submissions',
            'certificates',
            'lesson_progress',
            'lesson_notes',
            'lesson_bookmarks',
        ];

        $registry = app(AdminResourceRegistry::class);
        $this->assertCount(104, $registry->all());

        foreach ($resources as $resource) {
            $definition = $registry->get($resource);
            $this->actingAs($this->admin)
                ->get(route('admin.'.$definition->routeName.'.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('resource', $resource));
        }

        $this->actingAs($this->admin)
            ->get(route('admin.learning.quiz-attempts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', false)
                ->where('canEdit', false)
                ->where('canDelete', false));

        $this->actingAs($this->admin)
            ->get(route('admin.learning.quizzes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields.0.key', 'course_id')
                ->where('fields.0.options.0.value', $course->id)
                ->where('fields.1.key', 'course_lesson_id')
                ->where('fields.1.dependsOn', 'course_id')
                ->where('fields.1.options.0.value', $lesson->id)
                ->where('fields.1.options.0.parentValue', $course->id)
                ->where('filters.0.key', 'search')
                ->where('filters.2.key', 'category'));

        $this->actingAs($this->admin)
            ->get(route('admin.learning.certificates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', true)
                ->where('canEdit', true)
                ->where('canDelete', false));

        foreach (['admin.learning.correct_records', 'admin.learning.grade_submissions', 'admin.learning.revoke_certificates'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_learning_catalogs_generate_unique_slugs_and_sync_ordered_courses(): void
    {
        $firstCourse = Course::factory()->create(['title' => 'Foundations']);
        $secondCourse = Course::factory()->create(['title' => 'Operations']);

        foreach (['Platform Architect', 'Platform Architect'] as $title) {
            $this->actingAs($this->admin)->post(route('admin.learning.paths.store'), [
                'title' => $title,
                'description' => 'A structured learning path.',
                'course_ids' => [$secondCourse->id, $firstCourse->id],
                'status' => LearningCatalogStatus::Active->value,
                'sort_order' => 1,
                'metadata_content' => '{"audience":"teams"}',
            ])->assertRedirect();
        }

        $this->assertEqualsCanonicalizing(
            ['platform-architect', 'platform-architect-2'],
            LearningPath::query()->pluck('slug')->all(),
        );

        $path = LearningPath::query()->where('slug', 'platform-architect')->firstOrFail();
        $this->assertSame([$secondCourse->id, $firstCourse->id], $path->courses()->pluck('courses.id')->all());

        $this->actingAs($this->admin)->post(route('admin.learning.bundles.store'), [
            'title' => 'Team Bundle',
            'description' => 'Two production courses.',
            'course_ids' => [$firstCourse->id, $secondCourse->id],
            'price' => 249,
            'status' => LearningCatalogStatus::Active->value,
            'sort_order' => 2,
            'metadata_content' => '{"team_ready":true}',
        ])->assertRedirect();

        $this->assertDatabaseHas('course_bundles', ['slug' => 'team-bundle', 'price' => 249]);
        $this->assertDatabaseCount('course_bundle_courses', 2);
    }

    public function test_drip_quiz_question_and_assignment_rules_preserve_course_integrity_and_safe_html(): void
    {
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();
        $lesson = CourseLesson::factory()->create(['course_id' => $course->id]);
        $otherLesson = CourseLesson::factory()->create(['course_id' => $otherCourse->id]);

        $this->actingAs($this->admin)->post(route('admin.learning.drip-schedules.store'), [
            'course_id' => $course->id,
            'course_lesson_id' => $otherLesson->id,
            'release_type' => DripReleaseType::Immediate->value,
            'is_active' => true,
        ])->assertSessionHasErrors('course_lesson_id');

        $this->actingAs($this->admin)->post(route('admin.learning.drip-schedules.store'), [
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'release_type' => DripReleaseType::DaysAfterEnrollment->value,
            'release_after_days' => 5,
            'is_active' => true,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.learning.quizzes.store'), [
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'title' => 'Architecture Check',
            'pass_score' => 70,
            'max_attempts' => 3,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();

        $quiz = Quiz::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.learning.quiz-questions.store'), [
            'quiz_id' => $quiz->id,
            'question' => 'Which boundary owns access?',
            'type' => 'multiple_choice',
            'points' => 2,
            'options_content' => '["Learning","Catalog"]',
            'correct_answer_content' => '["Learning"]',
            'explanation' => '<p>Use the learning service.</p><script>alert(1)</script>',
            'sort_order' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.learning.assignments.store'), [
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'title' => 'Map the Domain',
            'instructions' => '<h2>Deliverable</h2><pre><code class="language-php">final class Map {}</code></pre><script>bad()</script>',
            'pass_score' => 70,
            'max_points' => 100,
            'allow_file_uploads' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('lesson_drip_schedules', ['course_lesson_id' => $lesson->id, 'release_after_days' => 5]);
        $this->assertStringNotContainsString('<script', (string) $quiz->questions()->firstOrFail()->explanation);
        $assignment = Assignment::query()->firstOrFail();
        $this->assertStringContainsString('language-php', $assignment->instructions);
        $this->assertStringNotContainsString('<script', $assignment->instructions);
    }

    public function test_student_activity_tables_reject_unrestricted_crud(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $submission = AssignmentSubmission::factory()->create();
        $progress = LessonProgress::factory()->create();
        $note = LessonNote::factory()->create();
        $bookmark = LessonBookmark::factory()->create();

        $cases = [
            ['quiz-attempts', $attempt->id],
            ['assignment-submissions', $submission->id],
            ['progress', $progress->id],
            ['notes', $note->id],
            ['bookmarks', $bookmark->id],
        ];

        foreach ($cases as [$routeKey, $id]) {
            $this->actingAs($this->admin)
                ->post(route('admin.learning.'.$routeKey.'.store'), [])
                ->assertStatus(405);
            $this->actingAs($this->admin)
                ->patch(route('admin.learning.'.$routeKey.'.update', $id), [])
                ->assertStatus(405);
            $this->actingAs($this->admin)
                ->delete(route('admin.learning.'.$routeKey.'.destroy', $id))
                ->assertStatus(405);
        }
    }

    public function test_quiz_attempt_corrections_require_sensitive_permission_and_are_audited(): void
    {
        [$course, $lesson] = $this->courseAndLesson();
        $quiz = Quiz::factory()->create(['course_id' => $course->id, 'course_lesson_id' => $lesson->id, 'pass_score' => 70]);
        $student = User::factory()->create();
        $attempt = QuizAttempt::factory()->create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'score' => 5,
            'max_score' => 10,
            'status' => QuizAttemptStatus::Submitted,
        ]);

        $reviewer = User::factory()->create();
        $reviewer->assignRole(RoleName::SubAdmin->value);
        $reviewer->givePermissionTo(['admin.quiz_attempts.view', 'admin.quiz_attempts.manage']);

        $this->actingAs($reviewer)
            ->get(route('admin.learning.records.show', ['type' => 'quiz-attempt', 'id' => $attempt->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/learning/RecordReview')
                ->where('record.status', QuizAttemptStatus::Submitted->value));

        $this->actingAs($reviewer)
            ->patch(route('admin.learning.records.update', ['type' => 'quiz-attempt', 'id' => $attempt->id]), [
                'status' => QuizAttemptStatus::Passed->value,
                'score' => 8,
                'max_score' => 10,
                'reason' => 'Manual review confirmed the corrected answer.',
            ])
            ->assertForbidden();

        $reviewer->givePermissionTo('admin.learning.correct_records');
        $this->actingAs($reviewer)
            ->patch(route('admin.learning.records.update', ['type' => 'quiz-attempt', 'id' => $attempt->id]), [
                'status' => QuizAttemptStatus::Passed->value,
                'score' => 8,
                'max_score' => 10,
                'reason' => 'Manual review confirmed the corrected answer.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_attempts', ['id' => $attempt->id, 'score' => 8, 'passed' => true, 'status' => QuizAttemptStatus::Passed->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.learning.quiz_attempt_corrected', 'actor_id' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->patch(route('admin.learning.records.update', ['type' => 'quiz-attempt', 'id' => $attempt->id]), [
                'status' => QuizAttemptStatus::Failed->value,
                'score' => 9,
                'max_score' => 10,
                'reason' => 'Attempting an inconsistent correction.',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_assignment_grading_is_validated_and_records_the_reviewer(): void
    {
        [$course, $lesson] = $this->courseAndLesson();
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'max_points' => 100,
            'pass_score' => 70,
        ]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.learning.records.update', ['type' => 'assignment-submission', 'id' => $submission->id]), [
                'status' => AssignmentSubmissionStatus::Passed->value,
                'score' => 84,
                'feedback' => 'Clear architecture and strong boundary choices.',
                'reason' => 'Initial instructor grading completed.',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame($this->admin->id, $submission->graded_by);
        $this->assertTrue($submission->passed);
        $this->assertNotNull($submission->graded_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.learning.assignment_submission_graded']);

        $this->actingAs($this->admin)
            ->patch(route('admin.learning.records.update', ['type' => 'assignment-submission', 'id' => $submission->id]), [
                'status' => AssignmentSubmissionStatus::Passed->value,
                'score' => 120,
                'reason' => 'This invalid score must be rejected.',
            ])
            ->assertSessionHasErrors('score');
    }

    public function test_progress_corrections_enforce_completion_invariants_and_preserve_history(): void
    {
        [$course, $lesson] = $this->courseAndLesson();
        $progress = LessonProgress::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'progress_seconds' => 180,
            'duration_seconds' => 300,
            'progress_percent' => 60,
        ]);

        $url = route('admin.learning.records.update', ['type' => 'lesson-progress', 'id' => $progress->id]);
        $this->actingAs($this->admin)->patch($url, [
            'status' => LessonProgressStatus::Completed->value,
            'progress_seconds' => 300,
            'duration_seconds' => 300,
            'progress_percent' => 99,
            'reason' => 'Completion import correction.',
        ])->assertSessionHasErrors('progress_percent');

        $this->actingAs($this->admin)->patch($url, [
            'status' => LessonProgressStatus::Completed->value,
            'progress_seconds' => 300,
            'duration_seconds' => 300,
            'progress_percent' => 100,
            'reason' => 'Completion import correction.',
        ])->assertRedirect();

        $progress->refresh();
        $this->assertSame(100, $progress->progress_percent);
        $this->assertNotNull($progress->completed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.learning.progress_corrected']);
    }

    public function test_certificates_are_issued_with_unique_codes_and_revoked_only_through_audited_action(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.learning.certificates.store'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'issued_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'metadata_content' => '{"source":"admin"}',
        ])->assertRedirect();

        $certificate = Certificate::query()->firstOrFail();
        $this->assertNotEmpty($certificate->certificate_number);
        $this->assertNotEmpty($certificate->verification_code);
        $this->assertSame(CertificateStatus::Active, $certificate->status);

        $this->actingAs($this->admin)
            ->patch(route('admin.learning.records.update', ['type' => 'certificate', 'id' => $certificate->id]), [
                'status' => CertificateStatus::Revoked->value,
                'expires_at' => null,
                'reason' => 'Certificate issued against an invalid completion record.',
            ])
            ->assertRedirect();

        $this->assertSame(CertificateStatus::Revoked, $certificate->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.learning.certificate_status_changed']);

        $this->actingAs($this->admin)
            ->delete(route('admin.learning.certificates.destroy', $certificate))
            ->assertSessionHasErrors('record');
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
    }

    public function test_enrollments_quizzes_and_assignments_with_activity_cannot_be_deleted(): void
    {
        [$course, $lesson] = $this->courseAndLesson();
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'last_lesson_id' => $lesson->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);
        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $quiz = Quiz::factory()->create(['course_id' => $course->id, 'course_lesson_id' => $lesson->id]);
        QuizAttempt::factory()->create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $assignment = Assignment::factory()->create(['course_id' => $course->id, 'course_lesson_id' => $lesson->id]);
        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.learning.enrollments.destroy', $enrollment))
            ->assertSessionHasErrors('record');
        $this->actingAs($this->admin)
            ->delete(route('admin.learning.quizzes.destroy', $quiz))
            ->assertSessionHasErrors('record');
        $this->actingAs($this->admin)
            ->delete(route('admin.learning.assignments.destroy', $assignment))
            ->assertSessionHasErrors('record');
    }

    /** @return array{Course, CourseLesson} */
    private function courseAndLesson(): array
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->create(['course_id' => $course->id]);

        return [$course, $lesson];
    }
}
