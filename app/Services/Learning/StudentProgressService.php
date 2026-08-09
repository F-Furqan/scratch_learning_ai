<?php

namespace App\Services\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PublishStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Analytics\AnalyticsEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentProgressService
{
    public function __construct(
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function enroll(User $user, Course $course, string $source = 'manual'): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()->firstOrNew([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        $enrollment->forceFill([
            'source' => $enrollment->exists ? $enrollment->source : $source,
            'status' => $enrollment->exists ? $enrollment->status : CourseEnrollmentStatus::Active,
            'started_at' => $enrollment->started_at ?? now(),
        ])->save();

        return $enrollment;
    }

    public function recordLessonProgress(User $user, CourseLesson $lesson, int $progressSeconds, ?int $durationSeconds, bool $completed = false): LessonProgress
    {
        return DB::transaction(function () use ($user, $lesson, $progressSeconds, $durationSeconds, $completed): LessonProgress {
            $lesson->loadMissing('course');

            /** @var Course $course */
            $course = $lesson->course;
            $percent = $this->lessonPercent($progressSeconds, $durationSeconds, $completed);
            $isCompleted = $completed || $percent >= 95;
            $status = $isCompleted ? LessonProgressStatus::Completed : LessonProgressStatus::InProgress;

            $progress = LessonProgress::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'course_lesson_id' => $lesson->id,
                ],
                [
                    'course_id' => $course->id,
                    'status' => $status,
                    'progress_seconds' => max(0, $progressSeconds),
                    'duration_seconds' => $durationSeconds ? max(1, $durationSeconds) : null,
                    'progress_percent' => $percent,
                    'started_at' => LessonProgress::query()
                        ->where('user_id', $user->id)
                        ->where('course_lesson_id', $lesson->id)
                        ->value('started_at') ?? now(),
                    'last_watched_at' => now(),
                    'completed_at' => $isCompleted ? now() : null,
                ],
            );

            $this->refreshCourseProgress($user, $course, $lesson);
            $this->issueCertificateIfEligible($user, $course);
            $this->analytics->trackLessonProgress($user, $lesson, $progress);

            return $progress;
        });
    }

    public function issueCertificateIfEligible(User $user, Course $course): ?Certificate
    {
        $existing = Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $this->courseCompletionRequirementsMet($user, $course)) {
            return null;
        }

        return Certificate::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'certificate_number' => $this->uniqueCertificateNumber(),
            'verification_code' => $this->uniqueVerificationCode(),
            'status' => CertificateStatus::Active,
            'issued_at' => now(),
            'metadata' => [
                'course_title' => $course->title,
                'student_name' => $user->name,
                'issued_by' => config('app.name', 'Scratch Learning'),
            ],
        ]);
    }

    public function gradeAssignment(AssignmentSubmission $submission, int $score, ?User $grader = null, ?string $feedback = null): AssignmentSubmission
    {
        $submission->loadMissing('assignment');

        /** @var Assignment $assignment */
        $assignment = $submission->assignment;
        $maxPoints = max(1, (int) $assignment->max_points);
        $percent = (int) round((max(0, $score) / $maxPoints) * 100);
        $passed = $percent >= (int) $assignment->pass_score;

        $submission->forceFill([
            'graded_by' => $grader?->id,
            'status' => $passed ? AssignmentSubmissionStatus::Passed : AssignmentSubmissionStatus::Failed,
            'score' => min(max(0, $score), $maxPoints),
            'passed' => $passed,
            'feedback' => $feedback,
            'graded_at' => now(),
        ])->save();

        $this->issueCertificateIfEligible($submission->user, $submission->course);

        return $submission;
    }

    private function refreshCourseProgress(User $user, Course $course, CourseLesson $lastLesson): void
    {
        $publishedLessonCount = CourseLesson::query()
            ->where('course_id', $course->id)
            ->where('status', PublishStatus::Published->value)
            ->count();

        $completedLessonCount = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', LessonProgressStatus::Completed->value)
            ->whereHas('lesson', fn ($query) => $query->where('status', PublishStatus::Published->value))
            ->count();

        $percent = $publishedLessonCount > 0
            ? min(100, (int) round(($completedLessonCount / $publishedLessonCount) * 100))
            : 0;
        $completed = $publishedLessonCount > 0 && $completedLessonCount >= $publishedLessonCount;
        $enrollment = $this->enroll($user, $course, 'lesson_progress');

        $enrollment->forceFill([
            'last_lesson_id' => $lastLesson->id,
            'progress_percent' => $percent,
            'status' => $completed ? CourseEnrollmentStatus::Completed : CourseEnrollmentStatus::Active,
            'completed_at' => $completed ? ($enrollment->completed_at ?? now()) : null,
        ])->save();
    }

    private function courseCompletionRequirementsMet(User $user, Course $course): bool
    {
        return $this->publishedLessonsComplete($user, $course)
            && $this->requiredQuizzesPassed($user, $course)
            && $this->requiredAssignmentsPassed($user, $course);
    }

    private function publishedLessonsComplete(User $user, Course $course): bool
    {
        $lessonIds = CourseLesson::query()
            ->where('course_id', $course->id)
            ->where('status', PublishStatus::Published->value)
            ->pluck('id');

        if ($lessonIds->isEmpty()) {
            return false;
        }

        $completed = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('course_lesson_id', $lessonIds)
            ->where('status', LessonProgressStatus::Completed->value)
            ->distinct('course_lesson_id')
            ->count('course_lesson_id');

        return $completed >= $lessonIds->count();
    }

    private function requiredQuizzesPassed(User $user, Course $course): bool
    {
        $quizIds = Quiz::query()
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        if ($quizIds->isEmpty()) {
            return true;
        }

        $passed = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->distinct('quiz_id')
            ->count('quiz_id');

        return $passed >= $quizIds->count();
    }

    private function requiredAssignmentsPassed(User $user, Course $course): bool
    {
        $assignmentIds = Assignment::query()
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        if ($assignmentIds->isEmpty()) {
            return true;
        }

        $passed = AssignmentSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('passed', true)
            ->distinct('assignment_id')
            ->count('assignment_id');

        return $passed >= $assignmentIds->count();
    }

    private function lessonPercent(int $progressSeconds, ?int $durationSeconds, bool $completed): int
    {
        if ($completed) {
            return 100;
        }

        if (! $durationSeconds || $durationSeconds <= 0) {
            return min(100, max(0, $progressSeconds > 0 ? 10 : 0));
        }

        return min(100, max(0, (int) round(($progressSeconds / $durationSeconds) * 100)));
    }

    private function uniqueCertificateNumber(): string
    {
        do {
            $value = 'CERT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Certificate::query()->where('certificate_number', $value)->exists());

        return $value;
    }

    private function uniqueVerificationCode(): string
    {
        do {
            $value = Str::lower(Str::random(40));
        } while (Certificate::query()->where('verification_code', $value)->exists());

        return $value;
    }
}
