<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\DiscussionThread;
use App\Models\User;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\DiscussionService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentDiscussionController extends Controller
{
    public function thread(Request $request, DiscussionForum $forum, CommunityAccessService $access, DiscussionService $discussions): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $forum->loadMissing('course');

        abort_unless($access->canAccessForum($user, $forum), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
        ]);

        $thread = $discussions->createThread(
            $forum,
            $user,
            trim((string) $validated['title']),
            trim((string) $validated['body']),
        );

        return $this->respond($request, [
            'data' => [
                'id' => $thread->id,
                'status' => $this->enumValue($thread->getAttribute('status')),
            ],
        ], 201, 'Thread submitted for moderation.');
    }

    public function post(Request $request, DiscussionThread $thread, CommunityAccessService $access, DiscussionService $discussions): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $thread->loadMissing('forum.course');

        abort_unless(! (bool) $thread->is_locked, 403);
        abort_unless($access->canAccessForum($user, $thread->forum), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:8000'],
            'parent_id' => ['nullable', 'integer', 'exists:discussion_posts,id'],
        ]);

        $post = $discussions->createPost(
            $thread,
            $user,
            trim((string) $validated['body']),
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        );

        return $this->respond($request, [
            'data' => [
                'id' => $post->id,
                'status' => $this->enumValue($post->getAttribute('status')),
            ],
        ], 201, 'Reply submitted for moderation.');
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
