<?php

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'status' => LessonProgressStatus::InProgress,
            'progress_seconds' => 60,
            'duration_seconds' => 300,
            'progress_percent' => 20,
            'started_at' => now()->subMinutes(10),
            'last_watched_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => LessonProgressStatus::Completed,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }
}
