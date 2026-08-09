<?php

namespace App\Services\Community;

use App\Enums\ReputationEventType;
use App\Models\CommunityReputationScore;
use App\Models\ReputationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    public function record(User $recipient, ?User $actor, ReputationEventType $type, int $points, ?Model $subject = null, ?string $reason = null): ReputationEvent
    {
        return DB::transaction(function () use ($recipient, $actor, $type, $points, $subject, $reason): ReputationEvent {
            $event = ReputationEvent::query()->create([
                'user_id' => $recipient->id,
                'actor_id' => $actor?->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'type' => $type,
                'points' => $points,
                'reason' => $reason,
            ]);

            $score = CommunityReputationScore::query()->firstOrCreate(
                ['user_id' => $recipient->id],
                ['points' => 0, 'level' => 'newcomer'],
            );

            $score->points += $points;
            $score->level = $this->levelFor($score->points);
            $score->save();

            return $event;
        });
    }

    private function levelFor(int $points): string
    {
        return match (true) {
            $points >= 500 => 'expert',
            $points >= 150 => 'trusted',
            $points >= 40 => 'active',
            default => 'newcomer',
        };
    }
}
