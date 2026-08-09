<?php

namespace Database\Factories;

use App\Enums\LearningCatalogStatus;
use App\Models\SkillTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillTrack>
 */
class SkillTrackFactory extends Factory
{
    protected $model = SkillTrack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'status' => LearningCatalogStatus::Active,
            'sort_order' => 0,
            'metadata' => ['skill' => fake()->word()],
        ];
    }
}
