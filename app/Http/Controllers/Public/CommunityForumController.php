<?php

namespace App\Http\Controllers\Public;

use App\Enums\CommunityContentStatus;
use App\Http\Controllers\Controller;
use App\Models\DiscussionForum;
use App\Models\DiscussionThread;
use App\Services\Community\CommunityAccessService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityForumController extends Controller
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly CommunityAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $forums = DiscussionForum::query()
            ->with(['course', 'category'])
            ->where('status', CommunityContentStatus::Approved->value)
            ->orderBy('sort_order')
            ->latest()
            ->get()
            ->filter(fn (DiscussionForum $forum): bool => $this->access->canAccessForum($request->user(), $forum))
            ->map(fn (DiscussionForum $forum): array => $this->forumPayload($forum))
            ->values()
            ->all();

        return Inertia::render('public/community/Forums', [
            'forums' => $forums,
        ]);
    }

    public function show(Request $request, DiscussionForum $forum): Response
    {
        $forum->loadMissing(['course', 'category']);
        abort_unless($this->access->canAccessForum($request->user(), $forum), 403);

        $threads = $forum->threads()
            ->with('user')
            ->where('status', CommunityContentStatus::Approved->value)
            ->orderByDesc('is_pinned')
            ->latest('last_activity_at')
            ->paginate(20)
            ->through(fn (DiscussionThread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'body' => $this->sanitizer->preview($thread->body, 32),
                'url' => route('public.community.threads.show', $thread),
                'author' => $thread->user?->name,
                'is_pinned' => (bool) $thread->is_pinned,
                'is_locked' => (bool) $thread->is_locked,
                'replies_count' => $thread->replies_count,
                'upvotes_count' => $thread->upvotes_count,
                'last_activity_at' => $this->isoDate($thread->getAttribute('last_activity_at')),
            ]);

        return Inertia::render('public/community/ForumShow', [
            'forum' => [
                ...$this->forumPayload($forum),
                'thread_store_url' => route('student.community.forums.threads.store', $forum),
            ],
            'threads' => $threads,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function forumPayload(DiscussionForum $forum): array
    {
        return [
            'id' => $forum->id,
            'title' => $forum->title,
            'slug' => $forum->slug,
            'description' => $this->sanitizer->plainText($forum->description),
            'visibility' => $this->enumValue($forum->getAttribute('visibility')),
            'threads_count' => $forum->threads_count,
            'posts_count' => $forum->posts_count,
            'url' => route('public.community.forums.show', $forum),
            'course' => $forum->course ? [
                'id' => $forum->course->id,
                'title' => $forum->course->title,
                'url' => route('public.courses.show', $forum->course->slug),
            ] : null,
            'category' => $forum->category ? [
                'id' => $forum->category->id,
                'name' => $forum->category->name,
            ] : null,
        ];
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
