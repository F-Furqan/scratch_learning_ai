<?php

namespace App\Http\Controllers\Public;

use App\Enums\CommunityContentStatus;
use App\Http\Controllers\Controller;
use App\Models\DiscussionThread;
use App\Services\Community\CommunityAccessService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityThreadController extends Controller
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly CommunityAccessService $access,
    ) {}

    public function show(Request $request, DiscussionThread $thread): Response
    {
        $thread->loadMissing(['forum.course', 'user']);

        abort_unless($this->enumValue($thread->getAttribute('status')) === CommunityContentStatus::Approved->value, 403);
        abort_unless($this->access->canAccessForum($request->user(), $thread->forum), 403);

        $posts = $thread->posts()
            ->with('user')
            ->where('status', CommunityContentStatus::Approved->value)
            ->oldest()
            ->get()
            ->map(fn ($post): array => [
                'id' => $post->id,
                'body' => $this->sanitizer->plainText($post->body),
                'author' => $post->user?->name,
                'upvotes_count' => $post->upvotes_count,
                'reaction_url' => route('student.community.posts.reactions.store', $post),
                'report_url' => route('student.community.posts.reports.store', $post),
                'created_at' => $this->isoDate($post->getAttribute('created_at')),
            ])
            ->all();

        return Inertia::render('public/community/ThreadShow', [
            'thread' => [
                'id' => $thread->id,
                'title' => $thread->title,
                'body' => $this->sanitizer->plainText($thread->body),
                'author' => $thread->user?->name,
                'forum' => [
                    'id' => $thread->forum->id,
                    'title' => $thread->forum->title,
                    'url' => route('public.community.forums.show', $thread->forum),
                ],
                'is_locked' => (bool) $thread->is_locked,
                'upvotes_count' => $thread->upvotes_count,
                'post_store_url' => route('student.community.threads.posts.store', $thread),
                'reaction_url' => route('student.community.threads.reactions.store', $thread),
                'report_url' => route('student.community.threads.reports.store', $thread),
                'created_at' => $this->isoDate($thread->getAttribute('created_at')),
            ],
            'posts' => $posts,
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
