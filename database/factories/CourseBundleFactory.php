<?php

namespace Database\Factories;

use App\Enums\LearningCatalogStatus;
use App\Models\CourseBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseBundle>
 */
class CourseBundleFactory extends Factory
{
    protected $model = CourseBundle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 99, 499),
            'status' => LearningCatalogStatus::Active,
            'sort_order' => 0,
            'metadata' => ['seat_eligible' => true],
        ];
    }
}
