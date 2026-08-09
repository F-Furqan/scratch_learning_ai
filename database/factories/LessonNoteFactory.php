<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonNote>
 */
class LessonNoteFactory extends Factory
{
    protected $model = LessonNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'body' => fake()->paragraph(),
            'is_private' => true,
        ];
    }
}
