<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseQuestion;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use App\Services\Community\ContentReportService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentReportController extends Controller
{
    public function question(Request $request, CourseQuestion $question, ContentReportService $reports): JsonResponse|RedirectResponse
    {
        return $this->store($request, $question, $reports);
    }

    public function answer(Request $request, LessonQuestionAnswer $answer, ContentReportService $reports): JsonResponse|RedirectResponse
    {
        return $this->store($request, $answer, $reports);
    }

    public function thread(Request $request, DiscussionThread $thread, ContentReportService $reports): JsonResponse|RedirectResponse
    {
        return $this->store($request, $thread, $reports);
    }

    public function post(Request $request, DiscussionPost $post, ContentReportService $reports): JsonResponse|RedirectResponse
    {
        return $this->store($request, $post, $reports);
    }

    private function store(Request $request, Model $reportable, ContentReportService $reports): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:128'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $reports->report($reportable, $user, [
            'reason' => trim((string) $validated['reason']),
            'details' => isset($validated['details']) ? trim((string) $validated['details']) : null,
        ]);

        return $this->respond($request, [
            'data' => [
                'id' => $report->id,
                'status' => $this->enumValue($report->getAttribute('status')),
            ],
        ], 201, 'Report submitted for moderation.');
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
