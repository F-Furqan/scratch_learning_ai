<?php

namespace Database\Factories;

use App\Models\CommunityReputationScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityReputationScore>
 */
class CommunityReputationScoreFactory extends Factory
{
    protected $model = CommunityReputationScore::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'points' => fake()->numberBetween(0, 250),
            'level' => 'newcomer',
        ];
    }
}
