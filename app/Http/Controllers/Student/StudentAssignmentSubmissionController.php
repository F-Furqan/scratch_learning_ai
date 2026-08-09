<?php

namespace App\Http\Controllers\Student;

use App\Enums\AssignmentSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Services\Learning\LearningAccessService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAssignmentSubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment, LearningAccessService $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $assignment->loadMissing(['course', 'lesson']);

        $canAccess = $assignment->lesson
            ? $access->canAccessLesson($user, $assignment->course, $assignment->lesson)
            : $access->canAccessCourse($user, $assignment->course);

        abort_unless($canAccess, 403);

        $validated = $request->validate([
            'submitted_text' => ['nullable', 'required_without:media_asset_id', 'string', 'max:20000'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ]);

        abort_if(isset($validated['media_asset_id']) && ! (bool) $assignment->allow_file_uploads, 422, 'File uploads are not enabled for this assignment.');

        $submission = AssignmentSubmission::query()->updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'course_id' => $assignment->course_id,
                'course_lesson_id' => $assignment->course_lesson_id,
                'media_asset_id' => $validated['media_asset_id'] ?? null,
                'status' => AssignmentSubmissionStatus::Submitted,
                'submitted_text' => $validated['submitted_text'] ?? null,
                'score' => null,
                'passed' => null,
                'feedback' => null,
                'submitted_at' => now(),
                'graded_at' => null,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'status' => $this->enumValue($submission->getAttribute('status')),
                'submitted_at' => $this->isoDate($submission->getAttribute('submitted_at')),
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

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }
}
