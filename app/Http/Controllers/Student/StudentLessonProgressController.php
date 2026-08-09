<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\User;
use App\Services\Learning\LearningAccessService;
use App\Services\Learning\StudentProgressService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLessonProgressController extends Controller
{
    public function store(Request $request, CourseLesson $lesson, LearningAccessService $access, StudentProgressService $progress): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lesson->loadMissing('course');

        abort_unless($access->canAccessLesson($user, $lesson->course, $lesson), 403);

        $validated = $request->validate([
            'progress_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $record = $progress->recordLessonProgress(
            $user,
            $lesson,
            (int) $validated['progress_seconds'],
            isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
            (bool) ($validated['completed'] ?? false),
        );

        return response()->json([
            'data' => [
                'id' => $record->id,
                'status' => $this->enumValue($record->getAttribute('status')),
                'progress_percent' => $record->progress_percent,
                'completed_at' => $this->isoDate($record->getAttribute('completed_at')),
            ],
        ]);
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
}
