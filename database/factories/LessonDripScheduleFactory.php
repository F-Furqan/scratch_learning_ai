<?php

namespace Database\Factories;

use App\Enums\DripReleaseType;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonDripSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonDripSchedule>
 */
class LessonDripScheduleFactory extends Factory
{
    protected $model = LessonDripSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'release_type' => DripReleaseType::Immediate,
            'release_after_days' => null,
            'release_at' => null,
            'is_active' => true,
        ];
    }

    public function afterEnrollment(int $days): static
    {
        return $this->state(fn (): array => [
            'release_type' => DripReleaseType::DaysAfterEnrollment,
            'release_after_days' => $days,
        ]);
    }
}
