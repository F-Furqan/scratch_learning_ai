<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Models\CourseQuestion;
use App\Models\ModerationQueueItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationQueueItem>
 */
class ModerationQueueItemFactory extends Factory
{
    protected $model = ModerationQueueItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => CourseQuestion::class,
            'subject_id' => CourseQuestion::factory(),
            'reporter_id' => User::factory(),
            'assigned_to' => null,
            'status' => CommunityContentStatus::Pending,
            'reason' => 'Needs moderation',
            'spam_score' => 0,
            'matched_terms' => [],
            'reviewed_at' => null,
            'resolution_note' => null,
        ];
    }
}
