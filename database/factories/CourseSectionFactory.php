<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSection>
 */
class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(12),
            'sort_order' => fake()->numberBetween(1, 10),
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
