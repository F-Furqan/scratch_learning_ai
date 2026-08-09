<?php

namespace App\Services\Community;

use App\Enums\CommunityReactionType;
use App\Enums\ReputationEventType;
use App\Models\CommunityReaction;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReactionService
{
    public function __construct(
        private readonly ReputationService $reputation,
    ) {}

    public function toggle(User $user, Model $reactable, CommunityReactionType $type = CommunityReactionType::Upvote): bool
    {
        return DB::transaction(function () use ($user, $reactable, $type): bool {
            $existing = CommunityReaction::query()
                ->where('user_id', $user->id)
                ->where('reactable_type', $reactable->getMorphClass())
                ->where('reactable_id', $reactable->getKey())
                ->where('type', $type->value)
                ->first();

            if ($existing) {
                $existing->delete();
                $this->incrementCounter($reactable, -1);

                return false;
            }

            CommunityReaction::query()->create([
                'user_id' => $user->id,
                'reactable_type' => $reactable->getMorphClass(),
                'reactable_id' => $reactable->getKey(),
                'type' => $type,
                'value' => 1,
            ]);

            $this->incrementCounter($reactable, 1);
            $this->awardForReaction($user, $reactable, $type);

            return true;
        });
    }

    private function incrementCounter(Model $reactable, int $amount): void
    {
        if (! in_array($reactable::class, [LessonQuestionAnswer::class, DiscussionThread::class, DiscussionPost::class], true)) {
            return;
        }

        $reactable->increment('upvotes_count', $amount);
    }

    private function awardForReaction(User $actor, Model $reactable, CommunityReactionType $type): void
    {
        if ($type !== CommunityReactionType::Upvote) {
            return;
        }

        $recipient = $reactable instanceof LessonQuestionAnswer || $reactable instanceof DiscussionThread || $reactable instanceof DiscussionPost
            ? $reactable->user
            : null;

        if (! $recipient || $recipient->is($actor)) {
            return;
        }

        $this->reputation->record($recipient, $actor, ReputationEventType::UpvoteReceived, 1, $reactable, 'Community upvote received.');
    }
}
