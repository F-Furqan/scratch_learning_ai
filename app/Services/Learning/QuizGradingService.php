<?php

namespace App\Services\Learning;

use App\Enums\QuizAttemptStatus;
use App\Enums\QuizQuestionType;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Arr;

class QuizGradingService
{
    public function __construct(
        private readonly StudentProgressService $progress,
    ) {}

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function submit(User $user, Quiz $quiz, array $answers): QuizAttempt
    {
        abort_unless((bool) $quiz->is_active, 404);

        $quiz->loadMissing(['course', 'lesson', 'questions']);

        $attemptCount = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        abort_if($attemptCount >= max(1, (int) $quiz->max_attempts), 422, 'Maximum quiz attempts reached.');

        [$score, $maxScore] = $this->grade($quiz, $answers);
        $percent = $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 0;
        $passed = $percent >= (int) $quiz->pass_score;

        /** @var Course $course */
        $course = $quiz->course;
        $attempt = QuizAttempt::query()->create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'course_id' => $course->id,
            'course_lesson_id' => $quiz->course_lesson_id,
            'attempt_number' => $attemptCount + 1,
            'status' => $passed ? QuizAttemptStatus::Passed : QuizAttemptStatus::Failed,
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $passed,
            'answers' => $answers,
            'started_at' => now(),
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $this->progress->issueCertificateIfEligible($user, $course);

        return $attempt;
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array{int, int}
     */
    private function grade(Quiz $quiz, array $answers): array
    {
        $score = 0;
        $maxScore = 0;

        foreach ($quiz->questions as $question) {
            $points = max(1, (int) $question->points);
            $maxScore += $points;

            if ($this->answerIsCorrect($question, $this->answerFor($answers, $question))) {
                $score += $points;
            }
        }

        return [$score, $maxScore];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    private function answerFor(array $answers, QuizQuestion $question): mixed
    {
        return $answers[$question->id]
            ?? $answers[(string) $question->id]
            ?? Arr::get($answers, (string) $question->id);
    }

    private function answerIsCorrect(QuizQuestion $question, mixed $given): bool
    {
        $type = $this->enumValue($question->getAttribute('type'));
        $expected = $this->answerList($question->correct_answer);

        return match ($type) {
            QuizQuestionType::MultiSelect->value => $this->sortedStrings($this->answerList($given)) === $this->sortedStrings($expected),
            QuizQuestionType::TrueFalse->value => $this->boolValue($given) === $this->boolValue($expected[0] ?? null),
            QuizQuestionType::ShortAnswer->value => $this->normalizedString($given) === $this->normalizedString($expected[0] ?? null),
            default => $this->normalizedString($given) === $this->normalizedString($expected[0] ?? null),
        };
    }

    /**
     * @return list<mixed>
     */
    private function answerList(mixed $answer): array
    {
        if (is_array($answer)) {
            return array_values($answer);
        }

        return $answer === null ? [] : [$answer];
    }

    /**
     * @param  list<mixed>  $answers
     * @return list<string>
     */
    private function sortedStrings(array $answers): array
    {
        $values = array_map(fn (mixed $answer): string => $this->normalizedString($answer), $answers);
        sort($values);

        return $values;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y'], true);
    }

    private function normalizedString(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return strtolower(trim((string) $value));
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
