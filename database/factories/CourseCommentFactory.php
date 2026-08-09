<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseComment>
 */
class CourseCommentFactory extends Factory
{
    protected $model = CourseComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => null,
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'status' => PublishStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
        ]);
    }
}
