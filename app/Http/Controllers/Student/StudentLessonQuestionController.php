<?php

namespace App\Http\Controllers\Student;

use App\Enums\PublishStatus;
use App\Enums\ReputationEventType;
use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\CourseQuestion;
use App\Models\User;
use App\Services\Community\ModerationService;
use App\Services\Community\ReputationService;
use App\Services\Learning\LearningAccessService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentLessonQuestionController extends Controller
{
    public function store(Request $request, CourseLesson $lesson, LearningAccessService $access, ModerationService $moderation, ReputationService $reputation): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lesson->loadMissing('course');

        abort_unless((bool) $lesson->allow_questions, 403);
        abort_unless($access->canAccessLesson($user, $lesson->course, $lesson), 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $question = CourseQuestion::query()->create([
            'course_id' => $lesson->course_id,
            'course_lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'title' => isset($validated['title']) ? trim((string) $validated['title']) : null,
            'body' => trim((string) $validated['body']),
            'status' => PublishStatus::Pending,
        ]);

        $moderation->moderate($question, $question->body, $user, 'lesson_question');
        $reputation->record($user, $user, ReputationEventType::QuestionAsked, 1, $question, 'Asked a lesson question.');

        return $this->respond($request, [
            'data' => [
                'id' => $question->id,
                'status' => $this->enumValue($question->getAttribute('status')),
            ],
        ], 201, 'Question submitted for moderation.');
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
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
