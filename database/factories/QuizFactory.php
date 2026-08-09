<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'pass_score' => 70,
            'max_attempts' => 3,
            'time_limit_minutes' => null,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => [
            'is_required' => true,
        ]);
    }
}
