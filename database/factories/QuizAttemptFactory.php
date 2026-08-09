<?php

namespace Database\Factories;

use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'quiz_id' => Quiz::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'attempt_number' => 1,
            'status' => QuizAttemptStatus::Submitted,
            'score' => 0,
            'max_score' => 1,
            'passed' => false,
            'answers' => [],
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'graded_at' => now(),
        ];
    }
}
