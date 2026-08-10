<?php

namespace Modules\Admin\Http\Requests\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizAttemptStatus;
use Illuminate\Validation\Rule;

final class UpdateLearningRecordRequest extends AbstractLearningRecordRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reason = ['reason' => ['required', 'string', 'min:5', 'max:2000']];

        return [
            ...$reason,
            ...match ($this->recordType()) {
                'quiz-attempt' => [
                    'status' => ['required', Rule::in([
                        QuizAttemptStatus::Submitted->value,
                        QuizAttemptStatus::Passed->value,
                        QuizAttemptStatus::Failed->value,
                    ])],
                    'score' => ['required', 'integer', 'min:0'],
                    'max_score' => ['required', 'integer', 'min:1'],
                ],
                'assignment-submission' => [
                    'status' => ['required', Rule::in([
                        AssignmentSubmissionStatus::Graded->value,
                        AssignmentSubmissionStatus::Passed->value,
                        AssignmentSubmissionStatus::Failed->value,
                    ])],
                    'score' => ['required', 'integer', 'min:0'],
                    'feedback' => ['nullable', 'string', 'max:10000'],
                ],
                'lesson-progress' => [
                    'status' => ['required', Rule::enum(LessonProgressStatus::class)],
                    'progress_seconds' => ['required', 'integer', 'min:0'],
                    'duration_seconds' => ['nullable', 'integer', 'min:1'],
                    'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
                ],
                'certificate' => [
                    'status' => ['required', Rule::enum(CertificateStatus::class)],
                    'expires_at' => ['nullable', 'date'],
                ],
                default => [],
            },
        ];
    }
}
