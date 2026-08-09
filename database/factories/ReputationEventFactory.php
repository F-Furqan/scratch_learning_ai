<?php

namespace Database\Factories;

use App\Enums\ReputationEventType;
use App\Models\LessonQuestionAnswer;
use App\Models\ReputationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReputationEvent>
 */
class ReputationEventFactory extends Factory
{
    protected $model = ReputationEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'subject_type' => LessonQuestionAnswer::class,
            'subject_id' => LessonQuestionAnswer::factory(),
            'type' => ReputationEventType::UpvoteReceived,
            'points' => 1,
            'reason' => 'Community engagement',
            'metadata' => null,
        ];
    }
}
