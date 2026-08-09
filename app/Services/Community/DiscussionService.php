<?php

namespace App\Services\Community;

use App\Enums\CommunityContentStatus;
use App\Models\DiscussionForum;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiscussionService
{
    public function __construct(
        private readonly ModerationService $moderation,
    ) {}

    public function createThread(DiscussionForum $forum, User $user, string $title, string $body): DiscussionThread
    {
        return DB::transaction(function () use ($forum, $user, $title, $body): DiscussionThread {
            $thread = DiscussionThread::query()->create([
                'discussion_forum_id' => $forum->id,
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'status' => CommunityContentStatus::Pending,
                'last_activity_at' => now(),
            ]);

            $this->moderation->moderate($thread, $title."\n".$body, $user, 'discussion_thread');
            $forum->increment('threads_count');

            return $thread;
        });
    }

    public function createPost(DiscussionThread $thread, User $user, string $body, ?int $parentId = null): DiscussionPost
    {
        return DB::transaction(function () use ($thread, $user, $body, $parentId): DiscussionPost {
            $post = DiscussionPost::query()->create([
                'discussion_thread_id' => $thread->id,
                'user_id' => $user->id,
                'parent_id' => $parentId,
                'body' => $body,
                'status' => CommunityContentStatus::Pending,
            ]);

            $this->moderation->moderate($post, $body, $user, 'discussion_post');
            $thread->increment('replies_count');
            $thread->forceFill(['last_activity_at' => now()])->save();
            $thread->forum()->increment('posts_count');

            return $post;
        });
    }
}
