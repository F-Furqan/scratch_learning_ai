<?php

namespace Database\Factories;

use App\Enums\QuizQuestionType;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    protected $model = QuizQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'question' => fake()->sentence(8),
            'type' => QuizQuestionType::MultipleChoice,
            'points' => 1,
            'options' => ['A', 'B', 'C'],
            'correct_answer' => ['A'],
            'explanation' => fake()->optional()->sentence(12),
            'sort_order' => 0,
        ];
    }
}
