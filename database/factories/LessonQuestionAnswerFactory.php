<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Models\CourseQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonQuestionAnswer>
 */
class LessonQuestionAnswerFactory extends Factory
{
    protected $model = LessonQuestionAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_question_id' => CourseQuestion::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'status' => CommunityContentStatus::Pending,
            'upvotes_count' => 0,
            'accepted_by' => null,
            'accepted_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunityContentStatus::Approved,
        ]);
    }
}
