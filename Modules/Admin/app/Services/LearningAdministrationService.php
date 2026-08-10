<?php

namespace Modules\Admin\Services;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\DripReleaseType;
use App\Enums\LearningCatalogStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizQuestionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LearningPath;
use App\Models\LessonBookmark;
use App\Models\LessonDripSchedule;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SkillTrack;
use App\Models\User;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LearningAdministrationService
{
    /** @var list<string> */
    public const RESOURCES = [
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

    /** @var list<string> */
    private const READ_ONLY_RESOURCES = [
        'quiz_attempts',
        'assignment_submissions',
        'lesson_progress',
        'lesson_notes',
        'lesson_bookmarks',
    ];

    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function readOnly(string $resource): bool
    {
        return in_array($resource, self::READ_ONLY_RESOURCES, true);
    }

    public function deletable(string $resource): bool
    {
        return ! $this->readOnly($resource) && $resource !== 'certificates';
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'course_enrollments' => ['student', 'course', 'status', 'source', 'progress', 'last_lesson', 'started_at'],
            'learning_paths' => ['title', 'status', 'courses_count', 'students_count', 'sort_order'],
            'course_bundles' => ['title', 'status', 'price', 'courses_count', 'students_count', 'sort_order'],
            'skill_tracks' => ['title', 'status', 'courses_count', 'sort_order'],
            'drip_schedules' => ['course', 'lesson', 'release_type', 'release_rule', 'status'],
            'quizzes' => ['title', 'course', 'lesson', 'pass_score', 'questions_count', 'attempts_count', 'status'],
            'quiz_questions' => ['question', 'quiz', 'type', 'points', 'sort_order'],
            'quiz_attempts' => ['student', 'quiz', 'course', 'attempt', 'status', 'score', 'passed', 'submitted_at'],
            'assignments' => ['title', 'course', 'lesson', 'max_points', 'pass_score', 'submissions_count', 'status'],
            'assignment_submissions' => ['student', 'assignment', 'course', 'status', 'score', 'passed', 'grader', 'submitted_at'],
            'certificates' => ['certificate_number', 'student', 'course', 'status', 'issued_at', 'expires_at'],
            'lesson_progress' => ['student', 'course', 'lesson', 'status', 'progress', 'watch_time', 'last_watched_at'],
            'lesson_notes' => ['student', 'course', 'lesson', 'note', 'privacy', 'created_at'],
            'lesson_bookmarks' => ['student', 'course', 'lesson', 'label', 'saved_at'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /** @return array<int, array<string, mixed>> */
    public function fields(string $resource): array
    {
        return match ($resource) {
            'course_enrollments' => [
                $this->field('user_id', 'Student', 'select', $this->userOptions(), true),
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('last_lesson_id', 'Last Lesson', 'select', $this->lessonOptions()) + ['dependsOn' => 'course_id'],
                $this->field('source', 'Source', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(CourseEnrollmentStatus::cases()), true),
                $this->field('started_at', 'Started At', 'text'),
                $this->field('completed_at', 'Completed At', 'text'),
            ],
            'learning_paths', 'skill_tracks' => $this->catalogFields(),
            'course_bundles' => [
                $this->field('title', 'Title', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('course_ids', 'Courses', 'multiselect', $this->courseOptions()),
                $this->field('price', 'Price', 'number', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(LearningCatalogStatus::cases()), true),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('metadata_content', 'Metadata JSON', 'json'),
            ],
            'drip_schedules' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('course_lesson_id', 'Lesson', 'select', $this->lessonOptions(), true) + ['dependsOn' => 'course_id'],
                $this->field('release_type', 'Release Type', 'select', $this->enumOptions(DripReleaseType::cases()), true),
                $this->field('release_after_days', 'Release After Days', 'number'),
                $this->field('release_at', 'Release At', 'text'),
                $this->field('is_active', 'Active', 'checkbox'),
            ],
            'quizzes' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('course_lesson_id', 'Lesson', 'select', $this->lessonOptions()) + ['dependsOn' => 'course_id'],
                $this->field('title', 'Title', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('pass_score', 'Pass Score %', 'number', required: true),
                $this->field('max_attempts', 'Max Attempts', 'number', required: true),
                $this->field('time_limit_minutes', 'Time Limit Minutes', 'number'),
                $this->field('is_required', 'Required', 'checkbox'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
            ],
            'quiz_questions' => [
                $this->field('quiz_id', 'Quiz', 'select', $this->quizOptions(), true),
                $this->field('question', 'Question', 'textarea', required: true),
                $this->field('type', 'Type', 'select', $this->enumOptions(QuizQuestionType::cases()), true),
                $this->field('points', 'Points', 'number', required: true),
                $this->field('options_content', 'Options JSON', 'json'),
                $this->field('correct_answer_content', 'Correct Answer JSON', 'json'),
                $this->field('explanation', 'Explanation', 'richtext'),
                $this->field('sort_order', 'Sort Order', 'number'),
            ],
            'assignments' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('course_lesson_id', 'Lesson', 'select', $this->lessonOptions()) + ['dependsOn' => 'course_id'],
                $this->field('title', 'Title', 'text', required: true),
                $this->field('instructions', 'Instructions', 'richtext', required: true),
                $this->field('pass_score', 'Pass Score %', 'number', required: true),
                $this->field('max_points', 'Max Points', 'number', required: true),
                $this->field('due_days_after_enrollment', 'Due Days After Enrollment', 'number'),
                $this->field('allow_file_uploads', 'Allow File Uploads', 'checkbox'),
                $this->field('is_required', 'Required', 'checkbox'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
            ],
            'certificates' => [
                $this->field('user_id', 'Student', 'select', $this->userOptions(), true),
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('certificate_number', 'Certificate Number', 'text'),
                $this->field('verification_code', 'Verification Code', 'text'),
                $this->field('issued_at', 'Issued At', 'text'),
                $this->field('expires_at', 'Expires At', 'text'),
                $this->field('metadata_content', 'Metadata JSON', 'json'),
            ],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];

        if (in_array($resource, ['course_enrollments', 'learning_paths', 'course_bundles', 'skill_tracks', 'quiz_attempts', 'assignment_submissions', 'certificates', 'lesson_progress'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->statusOptions($resource));
        }

        if (in_array($resource, ['drip_schedules', 'quizzes', 'assignments'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->activeOptions());
        }

        if (in_array($resource, ['course_enrollments', 'learning_paths', 'course_bundles', 'skill_tracks', 'drip_schedules', 'quizzes', 'quiz_attempts', 'assignments', 'certificates', 'lesson_progress', 'lesson_notes', 'lesson_bookmarks'], true)) {
            $filters[] = $this->field('category', 'Course', 'select', $this->courseOptions());
        }

        if ($resource === 'quiz_questions') {
            $filters[] = $this->field('category', 'Quiz', 'select', $this->quizOptions());
        }

        if ($resource === 'assignment_submissions') {
            $filters[] = $this->field('category', 'Assignment', 'select', $this->assignmentOptions());
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkActions(string $resource): array
    {
        if ($this->readOnly($resource) || $resource === 'certificates') {
            return [];
        }

        return match ($resource) {
            'course_enrollments' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(CourseEnrollmentStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'learning_paths', 'course_bundles', 'skill_tracks' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(LearningCatalogStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'drip_schedules', 'quizzes', 'assignments' => [
                $this->bulkAction('active', 'Set Active State', $this->activeOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            default => [$this->bulkAction('delete', 'Delete')],
        };
    }

    /** @return array<string, int> */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'course_enrollments' => ['active' => CourseEnrollment::query()->where('status', CourseEnrollmentStatus::Active->value)->count(), 'completed' => CourseEnrollment::query()->where('status', CourseEnrollmentStatus::Completed->value)->count()],
            'learning_paths' => ['total' => LearningPath::query()->count(), 'active' => LearningPath::query()->where('status', LearningCatalogStatus::Active->value)->count()],
            'course_bundles' => ['total' => CourseBundle::query()->count(), 'active' => CourseBundle::query()->where('status', LearningCatalogStatus::Active->value)->count()],
            'skill_tracks' => ['total' => SkillTrack::query()->count(), 'active' => SkillTrack::query()->where('status', LearningCatalogStatus::Active->value)->count()],
            'drip_schedules' => ['total' => LessonDripSchedule::query()->count(), 'active' => LessonDripSchedule::query()->where('is_active', true)->count()],
            'quizzes' => ['quizzes' => Quiz::query()->count(), 'attempts' => QuizAttempt::query()->count()],
            'quiz_questions' => ['questions' => QuizQuestion::query()->count(), 'quizzes' => Quiz::query()->count()],
            'quiz_attempts' => ['submitted' => QuizAttempt::query()->where('status', QuizAttemptStatus::Submitted->value)->count(), 'passed' => QuizAttempt::query()->where('passed', true)->count()],
            'assignments' => ['assignments' => Assignment::query()->count(), 'active' => Assignment::query()->where('is_active', true)->count()],
            'assignment_submissions' => ['submitted' => AssignmentSubmission::query()->where('status', AssignmentSubmissionStatus::Submitted->value)->count(), 'graded' => AssignmentSubmission::query()->whereNotNull('graded_at')->count()],
            'certificates' => ['active' => Certificate::query()->where('status', CertificateStatus::Active->value)->count(), 'revoked' => Certificate::query()->where('status', CertificateStatus::Revoked->value)->count()],
            'lesson_progress' => ['in_progress' => LessonProgress::query()->where('status', LessonProgressStatus::InProgress->value)->count(), 'completed' => LessonProgress::query()->where('status', LessonProgressStatus::Completed->value)->count()],
            'lesson_notes' => ['notes' => LessonNote::query()->count(), 'students' => LessonNote::query()->distinct()->count('user_id')],
            'lesson_bookmarks' => ['bookmarks' => LessonBookmark::query()->count(), 'students' => LessonBookmark::query()->distinct()->count('user_id')],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'course_enrollments' => $this->enrollmentRow($record),
            'learning_paths' => $this->catalogRow($record),
            'course_bundles' => $this->catalogRow($record, true),
            'skill_tracks' => $this->catalogRow($record),
            'drip_schedules' => $this->dripRow($record),
            'quizzes' => $this->quizRow($record),
            'quiz_questions' => $this->questionRow($record),
            'quiz_attempts' => $this->attemptRow($record),
            'assignments' => $this->assignmentRow($record),
            'assignment_submissions' => $this->submissionRow($record),
            'certificates' => $this->certificateRow($record),
            'lesson_progress' => $this->progressRow($record),
            'lesson_notes' => $this->noteRow($record),
            'lesson_bookmarks' => $this->bookmarkRow($record),
            default => [],
        };
    }

    public function persist(Request $request, string $resource, ?Model $record = null): Model
    {
        abort_if($this->readOnly($resource), 405, 'This learning activity record is read-only. Use its controlled review action.');

        return match ($resource) {
            'course_enrollments' => $this->persistEnrollment($request, $record),
            'learning_paths' => $this->persistCatalog($request, $record, new LearningPath),
            'course_bundles' => $this->persistCatalog($request, $record, new CourseBundle, true),
            'skill_tracks' => $this->persistCatalog($request, $record, new SkillTrack),
            'drip_schedules' => $this->persistDrip($request, $record),
            'quizzes' => $this->persistQuiz($request, $record),
            'quiz_questions' => $this->persistQuestion($request, $record),
            'assignments' => $this->persistAssignment($request, $record),
            'certificates' => $this->persistCertificate($request, $record),
            default => throw ValidationException::withMessages(['resource' => 'Unsupported learning resource.']),
        };
    }

    public function applyBulkMutation(string $resource, Model $record, string $action, ?string $value): bool
    {
        if ($action === 'status' && in_array($resource, ['course_enrollments', 'learning_paths', 'course_bundles', 'skill_tracks'], true)) {
            $record->setAttribute('status', $value);

            if ($resource === 'course_enrollments' && $value === CourseEnrollmentStatus::Completed->value) {
                $record->setAttribute('progress_percent', 100);
                $record->setAttribute('completed_at', $record->getAttribute('completed_at') ?: now());
            }

            $record->save();

            return true;
        }

        if ($action === 'active' && in_array($resource, ['drip_schedules', 'quizzes', 'assignments'], true)) {
            $record->setAttribute('is_active', $value === 'active');
            $record->save();

            return true;
        }

        return false;
    }

    public function guardDeletion(string $resource, Model $record): void
    {
        if (! $this->deletable($resource)) {
            throw ValidationException::withMessages(['record' => 'This learning record is retained for student history and cannot be deleted.']);
        }

        if ($resource === 'course_enrollments' && $record instanceof CourseEnrollment) {
            $hasActivity = LessonProgress::query()->where('user_id', $record->user_id)->where('course_id', $record->course_id)->exists()
                || QuizAttempt::query()->where('user_id', $record->user_id)->where('course_id', $record->course_id)->exists()
                || AssignmentSubmission::query()->where('user_id', $record->user_id)->where('course_id', $record->course_id)->exists()
                || Certificate::query()->where('user_id', $record->user_id)->where('course_id', $record->course_id)->exists();

            throw_if($hasActivity, ValidationException::withMessages(['record' => 'Enrollment has learning activity and cannot be deleted. Pause or expire it instead.']));
        }

        if ($resource === 'learning_paths' && $record instanceof LearningPath && $record->students()->exists()) {
            throw ValidationException::withMessages(['record' => 'Learning path has enrolled students and cannot be deleted. Archive it instead.']);
        }

        if ($resource === 'course_bundles' && $record instanceof CourseBundle && ($record->students()->exists() || $record->paymentProducts()->exists())) {
            throw ValidationException::withMessages(['record' => 'Bundle has enrollments or payment products and cannot be deleted. Archive it instead.']);
        }

        if ($resource === 'quizzes' && $record instanceof Quiz && $record->attempts()->exists()) {
            throw ValidationException::withMessages(['record' => 'Quiz has attempts and cannot be deleted. Deactivate it instead.']);
        }

        if ($resource === 'quiz_questions' && $record instanceof QuizQuestion && $record->quiz()->whereHas('attempts')->exists()) {
            throw ValidationException::withMessages(['record' => 'Question belongs to a quiz with attempts and cannot be deleted.']);
        }

        if ($resource === 'assignments' && $record instanceof Assignment && $record->submissions()->exists()) {
            throw ValidationException::withMessages(['record' => 'Assignment has submissions and cannot be deleted. Deactivate it instead.']);
        }
    }

    /** @return array<string, mixed> */
    private function enrollmentRow(Model $record): array
    {
        abort_unless($record instanceof CourseEnrollment, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->status),
            'source' => $record->source,
            'progress' => $record->progress_percent.'%',
            'last_lesson' => $record->lastLesson?->title,
            'started_at' => $this->dateTime($record->started_at),
            'form' => [
                'user_id' => $record->user_id,
                'course_id' => $record->course_id,
                'last_lesson_id' => $record->last_lesson_id,
                'source' => $record->source,
                'status' => $this->enumValue($record->status),
                'started_at' => $this->dateTime($record->started_at),
                'completed_at' => $this->dateTime($record->completed_at),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function catalogRow(Model $record, bool $withPrice = false): array
    {
        abort_unless($record instanceof LearningPath || $record instanceof CourseBundle || $record instanceof SkillTrack, 500);

        $row = [
            'id' => $record->getKey(),
            'title' => $record->title,
            'status' => $this->enumValue($record->status),
            'courses_count' => $record->getAttribute('courses_count') ?? $record->courses->count(),
            'students_count' => $record->getAttribute('students_count'),
            'sort_order' => $record->sort_order,
            'form' => [
                'title' => $record->title,
                'description' => $record->description,
                'course_ids' => $record->courses->pluck('id')->values()->all(),
                'status' => $this->enumValue($record->status),
                'sort_order' => $record->sort_order,
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];

        if ($withPrice && $record instanceof CourseBundle) {
            $row['price'] = number_format((float) $record->price, 2);
            $row['form']['price'] = $record->price;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function dripRow(Model $record): array
    {
        abort_unless($record instanceof LessonDripSchedule, 500);

        $releaseType = $this->enumValue($record->release_type);
        $releaseRule = match ($releaseType) {
            DripReleaseType::DaysAfterEnrollment->value => $record->release_after_days.' days after enrollment',
            DripReleaseType::SpecificDate->value => $this->dateTime($record->release_at),
            default => 'Immediately',
        };

        return [
            'id' => $record->id,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'release_type' => str($releaseType)->replace('_', ' ')->headline()->toString(),
            'release_rule' => $releaseRule,
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'form' => [
                'course_id' => $record->course_id,
                'course_lesson_id' => $record->course_lesson_id,
                'release_type' => $releaseType,
                'release_after_days' => $record->release_after_days,
                'release_at' => $this->dateTime($record->release_at),
                'is_active' => $record->is_active,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function quizRow(Model $record): array
    {
        abort_unless($record instanceof Quiz, 500);

        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'pass_score' => $record->pass_score.'%',
            'questions_count' => $record->getAttribute('questions_count'),
            'attempts_count' => $record->getAttribute('attempts_count'),
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'form' => Arr::only($record->toArray(), [
                'course_id', 'course_lesson_id', 'title', 'description', 'pass_score', 'max_attempts',
                'time_limit_minutes', 'is_required', 'is_active', 'sort_order',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function questionRow(Model $record): array
    {
        abort_unless($record instanceof QuizQuestion, 500);

        return [
            'id' => $record->id,
            'question' => Str::limit($record->question, 90),
            'quiz' => $record->quiz?->title,
            'type' => str($this->enumValue($record->type))->replace('_', ' ')->headline()->toString(),
            'points' => $record->points,
            'sort_order' => $record->sort_order,
            'form' => [
                'quiz_id' => $record->quiz_id,
                'question' => $record->question,
                'type' => $this->enumValue($record->type),
                'points' => $record->points,
                'options_content' => $this->jsonContent($record->options),
                'correct_answer_content' => $this->jsonContent($record->correct_answer),
                'explanation' => $record->explanation,
                'sort_order' => $record->sort_order,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function attemptRow(Model $record): array
    {
        abort_unless($record instanceof QuizAttempt, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'quiz' => $record->quiz?->title,
            'course' => $record->course?->title,
            'attempt' => '#'.$record->attempt_number,
            'status' => $this->enumValue($record->status),
            'score' => $record->score.'/'.$record->max_score,
            'passed' => $record->passed ? 'Yes' : 'No',
            'submitted_at' => $this->dateTime($record->submitted_at),
            'form' => [],
            'workflow_actions' => [$this->reviewAction('Review', 'quiz-attempt', $record->id)],
        ];
    }

    /** @return array<string, mixed> */
    private function assignmentRow(Model $record): array
    {
        abort_unless($record instanceof Assignment, 500);

        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'max_points' => $record->max_points,
            'pass_score' => $record->pass_score.'%',
            'submissions_count' => $record->getAttribute('submissions_count'),
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'form' => Arr::only($record->toArray(), [
                'course_id', 'course_lesson_id', 'title', 'instructions', 'pass_score', 'max_points',
                'due_days_after_enrollment', 'allow_file_uploads', 'is_required', 'is_active', 'sort_order',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function submissionRow(Model $record): array
    {
        abort_unless($record instanceof AssignmentSubmission, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'assignment' => $record->assignment?->title,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->status),
            'score' => $record->score === null ? null : $record->score.'/'.$record->assignment?->max_points,
            'passed' => $record->passed === null ? null : ($record->passed ? 'Yes' : 'No'),
            'grader' => $record->grader?->name,
            'submitted_at' => $this->dateTime($record->submitted_at),
            'form' => [],
            'workflow_actions' => [$this->reviewAction('Grade', 'assignment-submission', $record->id)],
        ];
    }

    /** @return array<string, mixed> */
    private function certificateRow(Model $record): array
    {
        abort_unless($record instanceof Certificate, 500);

        return [
            'id' => $record->id,
            'certificate_number' => $record->certificate_number,
            'student' => $record->user?->name,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->status),
            'issued_at' => $this->dateTime($record->issued_at),
            'expires_at' => $this->dateTime($record->expires_at),
            'form' => [
                'user_id' => $record->user_id,
                'course_id' => $record->course_id,
                'certificate_number' => $record->certificate_number,
                'verification_code' => $record->verification_code,
                'issued_at' => $this->dateTime($record->issued_at),
                'expires_at' => $this->dateTime($record->expires_at),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
            'workflow_actions' => [$this->reviewAction('Manage', 'certificate', $record->id)],
            'preview_url' => route('certificates.verify', $record->verification_code, false),
        ];
    }

    /** @return array<string, mixed> */
    private function progressRow(Model $record): array
    {
        abort_unless($record instanceof LessonProgress, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'status' => $this->enumValue($record->status),
            'progress' => $record->progress_percent.'%',
            'watch_time' => $this->duration($record->progress_seconds),
            'last_watched_at' => $this->dateTime($record->last_watched_at),
            'form' => [],
            'workflow_actions' => [$this->reviewAction('Review', 'lesson-progress', $record->id)],
        ];
    }

    /** @return array<string, mixed> */
    private function noteRow(Model $record): array
    {
        abort_unless($record instanceof LessonNote, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'note' => Str::limit($record->body, 100),
            'privacy' => $record->is_private ? 'Private' : 'Shared',
            'created_at' => $this->dateTime($record->created_at),
            'form' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function bookmarkRow(Model $record): array
    {
        abort_unless($record instanceof LessonBookmark, 500);

        return [
            'id' => $record->id,
            'student' => $record->user?->name,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'label' => $record->label,
            'saved_at' => $this->dateTime($record->saved_at),
            'form' => [],
        ];
    }

    private function persistEnrollment(Request $request, ?Model $record): CourseEnrollment
    {
        $enrollment = $record instanceof CourseEnrollment ? $record : new CourseEnrollment;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'last_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')],
            'source' => ['required', 'string', 'max:32'],
            'status' => ['required', Rule::enum(CourseEnrollmentStatus::class)],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $duplicate = CourseEnrollment::query()
            ->where('user_id', $data['user_id'])
            ->where('course_id', $data['course_id'])
            ->when($enrollment->exists, fn ($query) => $query->whereKeyNot($enrollment->getKey()))
            ->exists();

        throw_if($duplicate, ValidationException::withMessages(['user_id' => 'This student is already enrolled in the selected course.']));
        $this->ensureLessonBelongsToCourse($data['last_lesson_id'] ?? null, (int) $data['course_id'], 'last_lesson_id');

        if ($data['status'] === CourseEnrollmentStatus::Completed->value) {
            $data['completed_at'] ??= now();
            $data['progress_percent'] = 100;
        }

        $data['started_at'] ??= $enrollment->started_at ?? now();
        $enrollment->fill($data)->save();

        return $enrollment->refresh();
    }

    private function persistCatalog(Request $request, ?Model $record, LearningPath|CourseBundle|SkillTrack $prototype, bool $withPrice = false): LearningPath|CourseBundle|SkillTrack
    {
        $catalog = $record instanceof $prototype ? $record : $prototype;
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', Rule::exists('courses', 'id')],
            'status' => ['required', Rule::enum(LearningCatalogStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata_content' => ['nullable', 'string'],
        ];

        if ($withPrice) {
            $rules['price'] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);
        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));
        $data['metadata'] = $this->jsonValue($data['metadata_content'] ?? null, 'metadata_content');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['course_ids'], $data['metadata_content']);

        $catalog->fill($data)->save();
        $catalog->courses()->sync(collect($courseIds)->mapWithKeys(
            fn (int $courseId, int $index): array => [$courseId => ['sort_order' => $index + 1]],
        )->all());

        return $catalog->refresh();
    }

    private function persistDrip(Request $request, ?Model $record): LessonDripSchedule
    {
        $drip = $record instanceof LessonDripSchedule ? $record : new LessonDripSchedule;
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'course_lesson_id' => ['required', Rule::exists('course_lessons', 'id'), Rule::unique('lesson_drip_schedules', 'course_lesson_id')->ignore($drip->id)],
            'release_type' => ['required', Rule::enum(DripReleaseType::class)],
            'release_after_days' => ['nullable', 'integer', 'min:0', 'required_if:release_type,'.DripReleaseType::DaysAfterEnrollment->value],
            'release_at' => ['nullable', 'date', 'required_if:release_type,'.DripReleaseType::SpecificDate->value],
            'is_active' => ['boolean'],
        ]);

        $this->ensureLessonBelongsToCourse((int) $data['course_lesson_id'], (int) $data['course_id'], 'course_lesson_id');

        if ($data['release_type'] !== DripReleaseType::DaysAfterEnrollment->value) {
            $data['release_after_days'] = null;
        }

        if ($data['release_type'] !== DripReleaseType::SpecificDate->value) {
            $data['release_at'] = null;
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $drip->fill($data)->save();

        return $drip->refresh();
    }

    private function persistQuiz(Request $request, ?Model $record): Quiz
    {
        $quiz = $record instanceof Quiz ? $record : new Quiz;
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->ensureLessonBelongsToCourse($data['course_lesson_id'] ?? null, (int) $data['course_id'], 'course_lesson_id');
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $quiz->fill($data)->save();

        return $quiz->refresh();
    }

    private function persistQuestion(Request $request, ?Model $record): QuizQuestion
    {
        $question = $record instanceof QuizQuestion ? $record : new QuizQuestion;
        $data = $request->validate([
            'quiz_id' => ['required', Rule::exists('quizzes', 'id')],
            'question' => ['required', 'string'],
            'type' => ['required', Rule::enum(QuizQuestionType::class)],
            'points' => ['required', 'integer', 'min:1'],
            'options_content' => ['nullable', 'string'],
            'correct_answer_content' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['options'] = $this->jsonValue($data['options_content'] ?? null, 'options_content');
        $data['correct_answer'] = $this->jsonValue($data['correct_answer_content'] ?? null, 'correct_answer_content');
        $data['explanation'] = $this->sanitizer->richText($data['explanation'] ?? null);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['options_content'], $data['correct_answer_content']);
        $question->fill($data)->save();

        return $question->refresh();
    }

    private function persistAssignment(Request $request, ?Model $record): Assignment
    {
        $assignment = $record instanceof Assignment ? $record : new Assignment;
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_points' => ['required', 'integer', 'min:1'],
            'due_days_after_enrollment' => ['nullable', 'integer', 'min:0'],
            'allow_file_uploads' => ['boolean'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->ensureLessonBelongsToCourse($data['course_lesson_id'] ?? null, (int) $data['course_id'], 'course_lesson_id');
        $data['instructions'] = $this->sanitizer->richText($data['instructions']);
        $data['allow_file_uploads'] = (bool) ($data['allow_file_uploads'] ?? false);
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $assignment->fill($data)->save();

        return $assignment->refresh();
    }

    private function persistCertificate(Request $request, ?Model $record): Certificate
    {
        $certificate = $record instanceof Certificate ? $record : new Certificate;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'certificate_number' => ['nullable', 'string', 'max:255', Rule::unique('certificates', 'certificate_number')->ignore($certificate->id)],
            'verification_code' => ['nullable', 'string', 'max:255', Rule::unique('certificates', 'verification_code')->ignore($certificate->id)],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $duplicate = Certificate::query()
            ->where('user_id', $data['user_id'])
            ->where('course_id', $data['course_id'])
            ->when($certificate->exists, fn ($query) => $query->whereKeyNot($certificate->getKey()))
            ->exists();

        throw_if($duplicate, ValidationException::withMessages(['user_id' => 'This student already has a certificate for the selected course.']));
        $data['certificate_number'] = ($data['certificate_number'] ?? null) ?: $this->uniqueCertificateNumber();
        $data['verification_code'] = ($data['verification_code'] ?? null) ?: $this->uniqueVerificationCode();
        $data['issued_at'] ??= $certificate->issued_at ?? now();
        $data['metadata'] = $this->jsonValue($data['metadata_content'] ?? null, 'metadata_content');
        unset($data['metadata_content']);

        if (! $certificate->exists) {
            $data['status'] = CertificateStatus::Active->value;
        }

        $certificate->fill($data)->save();

        return $certificate->refresh();
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogFields(): array
    {
        return [
            $this->field('title', 'Title', 'text', required: true),
            $this->field('description', 'Description', 'textarea'),
            $this->field('course_ids', 'Courses', 'multiselect', $this->courseOptions()),
            $this->field('status', 'Status', 'select', $this->enumOptions(LearningCatalogStatus::cases()), true),
            $this->field('sort_order', 'Sort Order', 'number'),
            $this->field('metadata_content', 'Metadata JSON', 'json'),
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    private function userOptions(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => ['label' => $user->name.' ('.$user->email.')', 'value' => $user->id])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function courseOptions(): array
    {
        return Course::query()->orderBy('title')->get(['id', 'title'])
            ->map(fn (Course $course): array => ['label' => $course->title, 'value' => $course->id])
            ->all();
    }

    /** @return array<int, array{label: string, value: int, parentValue: int}> */
    private function lessonOptions(): array
    {
        return CourseLesson::query()->with('course:id,title')->orderBy('course_id')->orderBy('order_number')->get(['id', 'course_id', 'title'])
            ->map(fn (CourseLesson $lesson): array => [
                'label' => ($lesson->course?->title ? $lesson->course->title.' · ' : '').$lesson->title,
                'value' => $lesson->id,
                'parentValue' => $lesson->course_id,
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function quizOptions(): array
    {
        return Quiz::query()->with('course:id,title')->orderBy('title')->get(['id', 'course_id', 'title'])
            ->map(fn (Quiz $quiz): array => ['label' => ($quiz->course?->title ? $quiz->course->title.' · ' : '').$quiz->title, 'value' => $quiz->id])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function assignmentOptions(): array
    {
        return Assignment::query()->with('course:id,title')->orderBy('title')->get(['id', 'course_id', 'title'])
            ->map(fn (Assignment $assignment): array => ['label' => ($assignment->course?->title ? $assignment->course->title.' · ' : '').$assignment->title, 'value' => $assignment->id])
            ->all();
    }

    /** @param array<int, BackedEnum> $cases
     * @return array<int, array{label: string, value: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => [
            'label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(),
            'value' => (string) $case->value,
        ], $cases);
    }

    /** @return array<int, array{label: string, value: string}> */
    private function statusOptions(string $resource): array
    {
        return match ($resource) {
            'course_enrollments' => $this->enumOptions(CourseEnrollmentStatus::cases()),
            'learning_paths', 'course_bundles', 'skill_tracks' => $this->enumOptions(LearningCatalogStatus::cases()),
            'quiz_attempts' => $this->enumOptions(QuizAttemptStatus::cases()),
            'assignment_submissions' => $this->enumOptions(AssignmentSubmissionStatus::cases()),
            'certificates' => $this->enumOptions(CertificateStatus::cases()),
            'lesson_progress' => $this->enumOptions(LessonProgressStatus::cases()),
            default => [],
        };
    }

    /** @return array<int, array{label: string, value: string}> */
    private function activeOptions(): array
    {
        return [
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Inactive', 'value' => 'inactive'],
        ];
    }

    /** @param array<int, array<string, mixed>> $options
     * @return array<string, mixed>
     */
    private function field(string $key, string $label, string $type, array $options = [], bool $required = false): array
    {
        return compact('key', 'label', 'type', 'options', 'required');
    }

    /** @param array<int, array<string, mixed>> $options
     * @return array<string, mixed>
     */
    private function bulkAction(string $value, string $label, array $options = []): array
    {
        return compact('value', 'label', 'options');
    }

    /** @return array{label: string, url: string, tone: string, method: string} */
    private function reviewAction(string $label, string $type, int $id): array
    {
        return [
            'label' => $label,
            'url' => route('admin.learning.records.show', ['type' => $type, 'id' => $id], false),
            'tone' => 'default',
            'method' => 'get',
        ];
    }

    private function ensureLessonBelongsToCourse(mixed $lessonId, int $courseId, string $field): void
    {
        if (! filled($lessonId)) {
            return;
        }

        $belongs = CourseLesson::query()->whereKey($lessonId)->where('course_id', $courseId)->exists();
        throw_unless($belongs, ValidationException::withMessages([$field => 'The selected lesson does not belong to the selected course.']));
    }

    /** @return array<mixed>|null */
    private function jsonValue(?string $value, string $field): ?array
    {
        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'Enter valid JSON.']);
        }

        return $decoded;
    }

    private function jsonContent(mixed $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d H:i:s') : null;
    }

    private function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);

        return $minutes > 0 ? $minutes.'m '.($seconds % 60).'s' : $seconds.'s';
    }

    private function uniqueCertificateNumber(): string
    {
        do {
            $number = 'SL-'.now()->format('Y').'-'.Str::upper(Str::random(10));
        } while (Certificate::query()->where('certificate_number', $number)->exists());

        return $number;
    }

    private function uniqueVerificationCode(): string
    {
        do {
            $code = Str::lower(Str::random(32));
        } while (Certificate::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
