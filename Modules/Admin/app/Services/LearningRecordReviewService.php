<?php

namespace Modules\Admin\Services;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Services\Admin\AuditLogger;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\Learning\UpdateLearningRecordRequest;

final class LearningRecordReviewService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function find(string $type, int $id): QuizAttempt|AssignmentSubmission|LessonProgress|Certificate
    {
        return match ($type) {
            'quiz-attempt' => QuizAttempt::query()->with(['user', 'quiz.questions', 'course', 'lesson'])->findOrFail($id),
            'assignment-submission' => AssignmentSubmission::query()->with(['user', 'assignment', 'course', 'lesson', 'mediaAsset', 'grader'])->findOrFail($id),
            'lesson-progress' => LessonProgress::query()->with(['user', 'course', 'lesson'])->findOrFail($id),
            'certificate' => Certificate::query()->with(['user', 'course'])->findOrFail($id),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    public function payload(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        return [
            'record' => $this->recordSummary($type, $record),
            'details' => $this->details($type, $record),
            'evidence' => $this->evidence($type, $record),
            'fields' => $this->fields($type, $record),
            'values' => $this->values($type, $record),
            'history' => $this->history($record),
            'urls' => [
                'index' => $this->indexUrl($type),
                'update' => route('admin.learning.records.update', ['type' => $type, 'id' => $record->getKey()], false),
            ],
        ];
    }

    public function update(UpdateLearningRecordRequest $request, string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): Model
    {
        return DB::transaction(function () use ($request, $type, $record): Model {
            $locked = $this->lockedRecord($type, (int) $record->getKey());
            $locked->load($this->relations($type));
            $before = $locked->toArray();
            $data = $request->validated();

            match ($type) {
                'quiz-attempt' => $this->correctAttempt($locked, $data),
                'assignment-submission' => $this->gradeSubmission($request, $locked, $data),
                'lesson-progress' => $this->correctProgress($locked, $data),
                'certificate' => $this->changeCertificateStatus($locked, $data),
                default => abort(404),
            };

            $this->auditLogger->log(
                $request,
                $this->auditAction($type),
                $locked,
                $before,
                $locked->fresh()?->toArray(),
                ['reason' => $data['reason']],
            );

            return $locked->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function recordSummary(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        return [
            'id' => $record->getKey(),
            'type' => $type,
            'eyebrow' => match ($type) {
                'quiz-attempt' => 'Quiz attempt review',
                'assignment-submission' => 'Assignment grading',
                'lesson-progress' => 'Progress correction',
                'certificate' => 'Certificate lifecycle',
                default => 'Learning record review',
            },
            'title' => match (true) {
                $record instanceof QuizAttempt => $record->quiz->title,
                $record instanceof AssignmentSubmission => $record->assignment->title,
                $record instanceof LessonProgress => $record->lesson->title,
                $record instanceof Certificate => $record->certificate_number,
            },
            'subtitle' => $record->user->name.' · '.$record->course->title,
            'status' => $this->enumValue($record->getAttribute('status')),
        ];
    }

    /** @return array<int, array{label: string, value: string|null}> */
    private function details(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        return match ($type) {
            'quiz-attempt' => $record instanceof QuizAttempt ? [
                $this->detail('Student', $record->user?->name),
                $this->detail('Course', $record->course?->title),
                $this->detail('Lesson', $record->lesson?->title),
                $this->detail('Attempt', '#'.$record->attempt_number),
                $this->detail('Score', $record->score.'/'.$record->max_score),
                $this->detail('Submitted', $this->dateTime($record->submitted_at)),
                $this->detail('Graded', $this->dateTime($record->graded_at)),
            ] : [],
            'assignment-submission' => $record instanceof AssignmentSubmission ? [
                $this->detail('Student', $record->user?->name),
                $this->detail('Course', $record->course?->title),
                $this->detail('Lesson', $record->lesson?->title),
                $this->detail('Maximum points', (string) $record->assignment?->max_points),
                $this->detail('Grader', $record->grader?->name),
                $this->detail('Submitted', $this->dateTime($record->submitted_at)),
                $this->detail('Graded', $this->dateTime($record->graded_at)),
            ] : [],
            'lesson-progress' => $record instanceof LessonProgress ? [
                $this->detail('Student', $record->user?->name),
                $this->detail('Course', $record->course?->title),
                $this->detail('Lesson', $record->lesson?->title),
                $this->detail('Progress', $record->progress_percent.'%'),
                $this->detail('Watch time', $record->progress_seconds.' seconds'),
                $this->detail('Started', $this->dateTime($record->started_at)),
                $this->detail('Completed', $this->dateTime($record->completed_at)),
            ] : [],
            'certificate' => $record instanceof Certificate ? [
                $this->detail('Student', $record->user?->name),
                $this->detail('Course', $record->course?->title),
                $this->detail('Certificate', $record->certificate_number),
                $this->detail('Verification code', $record->verification_code),
                $this->detail('Issued', $this->dateTime($record->issued_at)),
                $this->detail('Expires', $this->dateTime($record->expires_at)),
            ] : [],
            default => [],
        };
    }

    /** @return array<int, array{label: string, value: string, format: string}> */
    private function evidence(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        return match ($type) {
            'quiz-attempt' => $record instanceof QuizAttempt ? [
                $this->evidenceItem('Submitted answers', $this->json($record->answers), 'json'),
            ] : [],
            'assignment-submission' => $record instanceof AssignmentSubmission ? array_values(array_filter([
                $this->evidenceItem('Student submission', $record->submitted_text ?: 'No written response.', 'text'),
                $record->mediaAsset ? $this->evidenceItem('Submitted file', $record->mediaAsset->title ?: $record->mediaAsset->path, 'text') : null,
                $record->feedback ? $this->evidenceItem('Current feedback', $record->feedback, 'text') : null,
            ])) : [],
            'lesson-progress' => $record instanceof LessonProgress ? [
                $this->evidenceItem('Last watched', $this->dateTime($record->last_watched_at) ?: 'Never recorded', 'text'),
            ] : [],
            'certificate' => $record instanceof Certificate ? [
                $this->evidenceItem('Public verification URL', route('certificates.verify', $record->verification_code, false), 'link'),
                $this->evidenceItem('Metadata', $this->json($record->metadata), 'json'),
            ] : [],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        $reason = $this->field('reason', 'Reason for this controlled change', 'textarea', required: true);

        return match ($type) {
            'quiz-attempt' => [
                $this->field('status', 'Review outcome', 'select', [
                    ['label' => 'Submitted / Awaiting Decision', 'value' => QuizAttemptStatus::Submitted->value],
                    ['label' => 'Passed', 'value' => QuizAttemptStatus::Passed->value],
                    ['label' => 'Failed', 'value' => QuizAttemptStatus::Failed->value],
                ], true),
                $this->field('score', 'Score', 'number', min: 0),
                $this->field('max_score', 'Maximum Score', 'number', min: 1),
                $reason,
            ],
            'assignment-submission' => [
                $this->field('status', 'Grading outcome', 'select', [
                    ['label' => 'Graded', 'value' => AssignmentSubmissionStatus::Graded->value],
                    ['label' => 'Passed', 'value' => AssignmentSubmissionStatus::Passed->value],
                    ['label' => 'Failed', 'value' => AssignmentSubmissionStatus::Failed->value],
                ], true),
                $this->field('score', 'Score', 'number', min: 0, max: $record instanceof AssignmentSubmission ? $record->assignment?->max_points : null),
                $this->field('feedback', 'Feedback', 'textarea'),
                $reason,
            ],
            'lesson-progress' => [
                $this->field('status', 'Progress status', 'select', $this->enumOptions(LessonProgressStatus::cases()), true),
                $this->field('progress_percent', 'Progress Percent', 'number', min: 0, max: 100),
                $this->field('progress_seconds', 'Watched Seconds', 'number', min: 0),
                $this->field('duration_seconds', 'Duration Seconds', 'number', min: 1),
                $reason,
            ],
            'certificate' => [
                $this->field('status', 'Certificate status', 'select', $this->enumOptions(CertificateStatus::cases()), true),
                $this->field('expires_at', 'Expires At', 'text'),
                $reason,
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function values(string $type, QuizAttempt|AssignmentSubmission|LessonProgress|Certificate $record): array
    {
        return match ($type) {
            'quiz-attempt' => $record instanceof QuizAttempt ? [
                'status' => $this->enumValue($record->status),
                'score' => $record->score,
                'max_score' => $record->max_score,
                'reason' => '',
            ] : [],
            'assignment-submission' => $record instanceof AssignmentSubmission ? [
                'status' => $this->enumValue($record->status) === AssignmentSubmissionStatus::Submitted->value
                    ? AssignmentSubmissionStatus::Graded->value
                    : $this->enumValue($record->status),
                'score' => $record->score ?? 0,
                'feedback' => $record->feedback ?? '',
                'reason' => '',
            ] : [],
            'lesson-progress' => $record instanceof LessonProgress ? [
                'status' => $this->enumValue($record->status),
                'progress_percent' => $record->progress_percent,
                'progress_seconds' => $record->progress_seconds,
                'duration_seconds' => $record->duration_seconds,
                'reason' => '',
            ] : [],
            'certificate' => $record instanceof Certificate ? [
                'status' => $this->enumValue($record->status),
                'expires_at' => $this->dateTime($record->expires_at),
                'reason' => '',
            ] : [],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function history(Model $record): array
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'like', 'admin.learning.%')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (AuditLog $log): array {
                $metadata = $log->getAttribute('metadata');
                $before = $log->getAttribute('before');
                $after = $log->getAttribute('after');

                return [
                    'id' => $log->id,
                    'action' => str($log->action)->afterLast('.')->replace('_', ' ')->headline()->toString(),
                    'actor' => $log->actor?->name,
                    'reason' => is_array($metadata) ? ($metadata['reason'] ?? null) : null,
                    'from_status' => is_array($before) ? ($before['status'] ?? null) : null,
                    'to_status' => is_array($after) ? ($after['status'] ?? null) : null,
                    'created_at' => $this->dateTime($log->created_at),
                ];
            })
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function correctAttempt(Model $record, array $data): void
    {
        abort_unless($record instanceof QuizAttempt, 500);
        $score = (int) $data['score'];
        $maxScore = (int) $data['max_score'];
        $status = (string) $data['status'];

        throw_if($score > $maxScore, ValidationException::withMessages(['score' => 'Score cannot exceed the maximum score.']));

        if ($status !== QuizAttemptStatus::Submitted->value) {
            $passedByScore = ($score / $maxScore) * 100 >= (int) $record->quiz?->pass_score;
            $selectedPassed = $status === QuizAttemptStatus::Passed->value;
            throw_if($passedByScore !== $selectedPassed, ValidationException::withMessages(['status' => 'Outcome does not match the quiz pass score.']));
        }

        $record->fill([
            'status' => $status,
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $status === QuizAttemptStatus::Passed->value,
            'graded_at' => $status === QuizAttemptStatus::Submitted->value ? null : now(),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function gradeSubmission(UpdateLearningRecordRequest $request, Model $record, array $data): void
    {
        abort_unless($record instanceof AssignmentSubmission, 500);
        $score = (int) $data['score'];
        $maxPoints = (int) $record->assignment?->max_points;
        $status = (string) $data['status'];

        throw_if($score > $maxPoints, ValidationException::withMessages(['score' => 'Score cannot exceed the assignment maximum.']));

        if ($status !== AssignmentSubmissionStatus::Graded->value) {
            $passedByScore = ($score / $maxPoints) * 100 >= (int) $record->assignment?->pass_score;
            $selectedPassed = $status === AssignmentSubmissionStatus::Passed->value;
            throw_if($passedByScore !== $selectedPassed, ValidationException::withMessages(['status' => 'Outcome does not match the assignment pass score.']));
        }

        $record->fill([
            'status' => $status,
            'score' => $score,
            'passed' => $status === AssignmentSubmissionStatus::Graded->value ? null : $status === AssignmentSubmissionStatus::Passed->value,
            'feedback' => $data['feedback'] ?? null,
            'graded_by' => $request->user()?->getKey(),
            'graded_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function correctProgress(Model $record, array $data): void
    {
        abort_unless($record instanceof LessonProgress, 500);
        $status = (string) $data['status'];
        $seconds = (int) $data['progress_seconds'];
        $duration = isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null;
        $percent = (int) $data['progress_percent'];

        throw_if($duration !== null && $seconds > $duration, ValidationException::withMessages(['progress_seconds' => 'Watched seconds cannot exceed the lesson duration.']));

        if ($status === LessonProgressStatus::Completed->value && $percent !== 100) {
            throw ValidationException::withMessages(['progress_percent' => 'Completed lessons must have 100% progress.']);
        }

        if ($status === LessonProgressStatus::NotStarted->value) {
            $seconds = 0;
            $percent = 0;
        }

        $record->fill([
            'status' => $status,
            'progress_seconds' => $seconds,
            'duration_seconds' => $duration,
            'progress_percent' => $percent,
            'started_at' => $status === LessonProgressStatus::NotStarted->value ? null : ($record->started_at ?: now()),
            'last_watched_at' => $status === LessonProgressStatus::NotStarted->value ? null : now(),
            'completed_at' => $status === LessonProgressStatus::Completed->value ? ($record->completed_at ?: now()) : null,
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function changeCertificateStatus(Model $record, array $data): void
    {
        abort_unless($record instanceof Certificate, 500);

        $issuedAt = $record->getAttribute('issued_at');

        if (filled($data['expires_at'] ?? null) && $issuedAt instanceof CarbonInterface && $issuedAt->gte((string) $data['expires_at'])) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be after the issue date.']);
        }

        $record->fill([
            'status' => $data['status'],
            'expires_at' => $data['expires_at'] ?? null,
        ])->save();
    }

    /** @return list<string> */
    private function relations(string $type): array
    {
        return match ($type) {
            'quiz-attempt' => ['user', 'quiz.questions', 'course', 'lesson'],
            'assignment-submission' => ['user', 'assignment', 'course', 'lesson', 'mediaAsset', 'grader'],
            'lesson-progress' => ['user', 'course', 'lesson'],
            'certificate' => ['user', 'course'],
            default => [],
        };
    }

    private function auditAction(string $type): string
    {
        return match ($type) {
            'quiz-attempt' => 'admin.learning.quiz_attempt_corrected',
            'assignment-submission' => 'admin.learning.assignment_submission_graded',
            'lesson-progress' => 'admin.learning.progress_corrected',
            'certificate' => 'admin.learning.certificate_status_changed',
            default => 'admin.learning.record_changed',
        };
    }

    private function lockedRecord(string $type, int $id): QuizAttempt|AssignmentSubmission|LessonProgress|Certificate
    {
        return match ($type) {
            'quiz-attempt' => QuizAttempt::query()->lockForUpdate()->findOrFail($id),
            'assignment-submission' => AssignmentSubmission::query()->lockForUpdate()->findOrFail($id),
            'lesson-progress' => LessonProgress::query()->lockForUpdate()->findOrFail($id),
            'certificate' => Certificate::query()->lockForUpdate()->findOrFail($id),
            default => abort(404),
        };
    }

    private function indexUrl(string $type): string
    {
        return match ($type) {
            'quiz-attempt' => route('admin.learning.quiz-attempts.index', absolute: false),
            'assignment-submission' => route('admin.learning.assignment-submissions.index', absolute: false),
            'lesson-progress' => route('admin.learning.progress.index', absolute: false),
            'certificate' => route('admin.learning.certificates.index', absolute: false),
            default => '/admin/dashboard',
        };
    }

    /** @return array{label: string, value: string|null} */
    private function detail(string $label, ?string $value): array
    {
        return compact('label', 'value');
    }

    /** @return array{label: string, value: string, format: string} */
    private function evidenceItem(string $label, string $value, string $format): array
    {
        return compact('label', 'value', 'format');
    }

    /** @param array<int, array<string, mixed>> $options
     * @return array<string, mixed>
     */
    private function field(string $name, string $label, string $type, array $options = [], bool $required = false, ?int $min = null, ?int $max = null): array
    {
        return compact('name', 'label', 'type', 'options', 'required', 'min', 'max');
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

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d H:i:s') : null;
    }

    private function json(mixed $value): string
    {
        if ($value === null || $value === []) {
            return 'No data recorded.';
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
