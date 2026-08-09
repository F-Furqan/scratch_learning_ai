<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\LessonNote;
use App\Models\User;
use App\Services\Learning\LearningAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentLessonNoteController extends Controller
{
    public function store(Request $request, CourseLesson $lesson, LearningAccessService $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lesson->loadMissing('course');

        abort_unless($access->canAccessLesson($user, $lesson->course, $lesson), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_private' => ['sometimes', 'boolean'],
        ]);

        $note = LessonNote::query()->create([
            'user_id' => $user->id,
            'course_id' => $lesson->course_id,
            'course_lesson_id' => $lesson->id,
            'body' => trim((string) $validated['body']),
            'is_private' => (bool) ($validated['is_private'] ?? true),
        ]);

        return response()->json(['data' => $note], 201);
    }

    public function destroy(Request $request, LessonNote $note): Response
    {
        abort_unless($note->user_id === $request->user()?->id, 403);

        $note->delete();

        return response()->noContent();
    }
}
