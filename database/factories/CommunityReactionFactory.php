<?php

namespace Database\Factories;

use App\Enums\CommunityReactionType;
use App\Models\CommunityReaction;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityReaction>
 */
class CommunityReactionFactory extends Factory
{
    protected $model = CommunityReaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reactable_type' => LessonQuestionAnswer::class,
            'reactable_id' => LessonQuestionAnswer::factory(),
            'type' => CommunityReactionType::Upvote,
            'value' => 1,
        ];
    }
}
