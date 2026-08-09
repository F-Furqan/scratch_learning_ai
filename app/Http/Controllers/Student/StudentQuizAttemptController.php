<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Learning\LearningAccessService;
use App\Services\Learning\QuizGradingService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentQuizAttemptController extends Controller
{
    public function store(Request $request, Quiz $quiz, LearningAccessService $access, QuizGradingService $grading): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $quiz->loadMissing(['course', 'lesson', 'questions']);

        $canAccess = $quiz->lesson
            ? $access->canAccessLesson($user, $quiz->course, $quiz->lesson)
            : $access->canAccessCourse($user, $quiz->course);

        abort_unless($canAccess, 403);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $attempt = $grading->submit($user, $quiz, $validated['answers']);

        return response()->json([
            'data' => [
                'id' => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'passed' => $attempt->passed,
                'status' => $this->enumValue($attempt->getAttribute('status')),
            ],
        ], 201);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
