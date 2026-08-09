<?php

namespace App\Http\Controllers\Student;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityReactionType;
use App\Http\Controllers\Controller;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\ReactionService;
use App\Services\Learning\LearningAccessService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityReactionController extends Controller
{
    public function answer(Request $request, LessonQuestionAnswer $answer, LearningAccessService $learningAccess, ReactionService $reactions): JsonResponse|RedirectResponse
    {
        $answer->loadMissing('question.course', 'question.lesson');
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->enumValue($answer->getAttribute('status')) === CommunityContentStatus::Approved->value, 403);
        abort_unless($answer->question->lesson && $learningAccess->canAccessLesson($user, $answer->question->course, $answer->question->lesson), 403);

        return $this->toggle($request, $answer, $reactions);
    }

    public function thread(Request $request, DiscussionThread $thread, CommunityAccessService $access, ReactionService $reactions): JsonResponse|RedirectResponse
    {
        $thread->loadMissing('forum.course');
        abort_unless($this->enumValue($thread->getAttribute('status')) === CommunityContentStatus::Approved->value, 403);
        abort_unless($access->canAccessForum($request->user(), $thread->forum), 403);

        return $this->toggle($request, $thread, $reactions);
    }

    public function post(Request $request, DiscussionPost $post, CommunityAccessService $access, ReactionService $reactions): JsonResponse|RedirectResponse
    {
        $post->loadMissing('thread.forum.course');
        abort_unless($this->enumValue($post->getAttribute('status')) === CommunityContentStatus::Approved->value, 403);
        abort_unless($access->canAccessForum($request->user(), $post->thread->forum), 403);

        return $this->toggle($request, $post, $reactions);
    }

    private function toggle(Request $request, Model $reactable, ReactionService $reactions): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:upvote,like,helpful'],
        ]);

        $active = $reactions->toggle(
            $user,
            $reactable,
            CommunityReactionType::from((string) ($validated['type'] ?? CommunityReactionType::Upvote->value)),
        );

        return $this->respond($request, [
            'data' => [
                'active' => $active,
                'upvotes_count' => (int) $reactable->fresh()?->getAttribute('upvotes_count'),
            ],
        ], message: $active ? 'Reaction added.' : 'Reaction removed.');
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
