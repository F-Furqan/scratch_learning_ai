<?php

namespace Database\Factories;

use App\Enums\LearningCatalogStatus;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningPath>
 */
class LearningPathFactory extends Factory
{
    protected $model = LearningPath::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => LearningCatalogStatus::Active,
            'sort_order' => 0,
            'metadata' => ['level' => fake()->randomElement(['beginner', 'intermediate', 'advanced'])],
        ];
    }
}
