<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseQuestion>
 */
class CourseQuestionFactory extends Factory
{
    protected $model = CourseQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => null,
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'answered_by' => null,
            'answer' => null,
            'answered_at' => null,
            'status' => PublishStatus::Pending,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'answered_by' => User::factory(),
            'answer' => fake()->paragraph(),
            'answered_at' => now(),
            'status' => PublishStatus::Published,
        ]);
    }
}
