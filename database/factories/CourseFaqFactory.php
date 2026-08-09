<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseFaq>
 */
class CourseFaqFactory extends Factory
{
    protected $model = CourseFaq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => null,
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 20),
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
