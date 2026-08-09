<?php

namespace Tests\Feature;

use App\Enums\CoursePurchaseStatus;
use App\Enums\DripReleaseType;
use App\Enums\LearningResourceAccess;
use App\Enums\QuizQuestionType;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CoursePurchase;
use App\Models\CourseResource;
use App\Models\LessonBookmark;
use App\Models\LessonDripSchedule;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Learning\StudentProgressService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_progress_dashboard_and_certificate_issuance(): void
    {
        $student = $this->student();
        $course = Course::factory()->free()->published()->create();
        $firstLesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'order_number' => 1,
        ]);
        $secondLesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'order_number' => 2,
        ]);

        $this->actingAs($student)
            ->postJson(route('student.lessons.progress.store', $firstLesson->id), [
                'progress_seconds' => 120,
                'duration_seconds' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 40);

        $this->assertDatabaseHas(LessonProgress::class, [
            'user_id' => $student->id,
            'course_lesson_id' => $firstLesson->id,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas(CourseEnrollment::class, [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'progress_percent' => 0,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('continue_watching', 1)
                ->where('continue_watching.0.lesson_title', $firstLesson->title),
            );

        $this->actingAs($student)->postJson(route('student.lessons.progress.store', $firstLesson->id), [
            'progress_seconds' => 300,
            'duration_seconds' => 300,
            'completed' => true,
        ])->assertOk();
        $this->actingAs($student)->postJson(route('student.lessons.progress.store', $secondLesson->id), [
            'progress_seconds' => 300,
            'duration_seconds' => 300,
            'completed' => true,
        ])->assertOk();

        $certificate = Certificate::query()->where('user_id', $student->id)->where('course_id', $course->id)->first();

        $this->assertNotNull($certificate);
        $this->assertDatabaseHas(CourseEnrollment::class, [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        $this->get(route('certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('certificate.is_valid', true)
                ->where('certificate.course_title', $course->title),
            );
    }

    public function test_notes_bookmarks_and_saved_lessons_are_student_scoped(): void
    {
        $student = $this->student();
        $otherStudent = $this->student();
        $course = Course::factory()->free()->published()->create();
        $lesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'content' => 'Open learning content.',
        ]);

        $noteId = $this->actingAs($student)
            ->postJson(route('student.lessons.notes.store', $lesson->id), [
                'body' => 'Review the module boundary notes.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($student)
            ->postJson(route('student.lessons.bookmarks.store', $lesson->id), [
                'label' => 'Architecture checkpoint',
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas(LessonNote::class, [
            'id' => $noteId,
            'user_id' => $student->id,
            'body' => 'Review the module boundary notes.',
        ]);
        $this->assertDatabaseHas(LessonBookmark::class, [
            'user_id' => $student->id,
            'course_lesson_id' => $lesson->id,
            'label' => 'Architecture checkpoint',
        ]);

        $this->actingAs($otherStudent)
            ->deleteJson(route('student.notes.destroy', $noteId))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.learning.bookmark.label', 'Architecture checkpoint')
                ->where('lesson.learning.notes.0.body', 'Review the module boundary notes.'),
            );
    }

    public function test_downloadable_resources_are_protected_by_course_access(): void
    {
        $student = $this->student();
        $course = Course::factory()->published()->create(['is_free' => false]);
        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'is_free' => false,
            'is_paid' => true,
        ]);
        $resource = CourseResource::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'access_level' => LearningResourceAccess::Purchased,
            'external_url' => 'https://resources.example.com/protected.pdf',
        ]);

        $this->actingAs($student)
            ->getJson(route('student.resources.download', $resource->id))
            ->assertForbidden();

        CoursePurchase::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => CoursePurchaseStatus::Active,
        ]);

        $this->actingAs($student)
            ->getJson(route('student.resources.download', $resource->id))
            ->assertOk()
            ->assertJsonPath('data.url', 'https://resources.example.com/protected.pdf');
    }

    public function test_quiz_attempts_are_graded_against_pass_fail_rules(): void
    {
        $student = $this->student();
        $course = Course::factory()->free()->published()->create();
        $lesson = CourseLesson::factory()->free()->published()->create(['course_id' => $course->id]);
        $quiz = Quiz::factory()->required()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'pass_score' => 100,
        ]);
        $question = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'type' => QuizQuestionType::MultipleChoice,
            'points' => 2,
            'options' => ['A', 'B'],
            'correct_answer' => ['A'],
        ]);

        $this->actingAs($student)
            ->postJson(route('student.quizzes.attempts.store', $quiz->id), [
                'answers' => [
                    $question->id => 'A',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.max_score', 2)
            ->assertJsonPath('data.passed', true);

        $this->assertDatabaseHas(QuizAttempt::class, [
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'score' => 2,
            'passed' => true,
        ]);
    }

    public function test_assignment_submissions_can_be_graded_with_pass_fail_rules(): void
    {
        $student = $this->student();
        $grader = User::factory()->create();
        $course = Course::factory()->free()->published()->create();
        $lesson = CourseLesson::factory()->free()->published()->create(['course_id' => $course->id]);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'pass_score' => 70,
            'max_points' => 100,
        ]);

        $submissionId = $this->actingAs($student)
            ->postJson(route('student.assignments.submissions.store', $assignment->id), [
                'submitted_text' => 'My domain model includes progress, assessments, and certificates.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->json('data.id');

        $submission = AssignmentSubmission::query()->findOrFail($submissionId);
        app(StudentProgressService::class)->gradeAssignment($submission, 82, $grader, 'Clear and complete.');

        $this->assertDatabaseHas(AssignmentSubmission::class, [
            'id' => $submissionId,
            'graded_by' => $grader->id,
            'status' => 'passed',
            'score' => 82,
            'passed' => true,
        ]);
    }

    public function test_drip_content_locks_and_unlocks_by_enrollment_age(): void
    {
        $student = $this->student();
        $course = Course::factory()->free()->published()->create();
        $lesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'content' => 'Released lesson body.',
        ]);
        LessonDripSchedule::factory()->afterEnrollment(7)->create([
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
            'release_type' => DripReleaseType::DaysAfterEnrollment,
            'release_after_days' => 7,
        ]);
        $enrollment = CourseEnrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'started_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.is_locked', true)
                ->where('lesson.content', null),
            );

        $enrollment->forceFill(['started_at' => now()->subDays(8)])->save();

        $this->actingAs($student)
            ->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.is_locked', false)
                ->where('lesson.content', 'Released lesson body.'),
            );
    }

    private function student(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Student->value);
        $user->studentProfile()->firstOrCreate();

        return $user;
    }
}
