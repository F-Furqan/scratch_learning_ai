<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use App\Services\Community\QuestionAnswerService;
use App\Services\Learning\LearningAccessService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentLessonQuestionAnswerController extends Controller
{
    public function store(Request $request, CourseQuestion $question, LearningAccessService $access, QuestionAnswerService $answers): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $question->loadMissing(['course', 'lesson']);

        abort_unless($question->lesson && (bool) $question->lesson->allow_questions, 403);
        abort_unless($access->canAccessLesson($user, $question->course, $question->lesson), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $answer = $answers->answer($question, $user, trim((string) $validated['body']));

        return $this->respond($request, [
            'data' => [
                'id' => $answer->id,
                'status' => $this->enumValue($answer->getAttribute('status')),
            ],
        ], 201, 'Answer submitted for moderation.');
    }

    public function accept(Request $request, CourseQuestion $question, LessonQuestionAnswer $answer, QuestionAnswerService $answers): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $question->loadMissing('course');

        abort_unless(
            (int) $question->user_id === (int) $user->id
                || (int) $question->course?->created_by === (int) $user->id
                || $user->can('manage_courses'),
            403,
        );

        $accepted = $answers->accept($question, $answer, $user);

        return $this->respond($request, [
            'data' => [
                'id' => $accepted->id,
                'accepted_at' => $this->isoDate($accepted->getAttribute('accepted_at')),
            ],
        ], message: 'Accepted answer selected.');
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload, int $status = 200, string $message = 'Saved.'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        return back()->with('success', $message);
    }
}
