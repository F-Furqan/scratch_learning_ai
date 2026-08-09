<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\LessonBookmark;
use App\Models\User;
use App\Services\Learning\LearningAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLessonBookmarkController extends Controller
{
    public function store(Request $request, CourseLesson $lesson, LearningAccessService $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lesson->loadMissing('course');

        abort_unless($access->canAccessLesson($user, $lesson->course, $lesson), 403);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'saved' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('saved', $validated) && ! (bool) $validated['saved']) {
            LessonBookmark::query()
                ->where('user_id', $user->id)
                ->where('course_lesson_id', $lesson->id)
                ->delete();

            return response()->json(['data' => ['saved' => false]]);
        }

        $bookmark = LessonBookmark::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'course_lesson_id' => $lesson->id,
            ],
            [
                'course_id' => $lesson->course_id,
                'label' => $validated['label'] ?? null,
                'saved_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $bookmark->id,
                'saved' => true,
                'label' => $bookmark->label,
            ],
        ]);
    }
}
