<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonBookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonBookmark>
 */
class LessonBookmarkFactory extends Factory
{
    protected $model = LessonBookmark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'label' => fake()->optional()->words(3, true),
            'saved_at' => now(),
        ];
    }
}
