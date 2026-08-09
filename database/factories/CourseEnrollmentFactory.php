<?php

namespace Database\Factories;

use App\Enums\CourseEnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEnrollment>
 */
class CourseEnrollmentFactory extends Factory
{
    protected $model = CourseEnrollment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'last_lesson_id' => null,
            'source' => 'manual',
            'status' => CourseEnrollmentStatus::Active,
            'progress_percent' => 0,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(?CourseLesson $lesson = null): static
    {
        return $this->state(fn (): array => [
            'last_lesson_id' => $lesson?->id,
            'status' => CourseEnrollmentStatus::Completed,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }
}
