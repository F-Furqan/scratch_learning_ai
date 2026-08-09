<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraphs(2, true),
            'pass_score' => 70,
            'max_points' => 100,
            'due_days_after_enrollment' => null,
            'allow_file_uploads' => false,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => [
            'is_required' => true,
        ]);
    }
}
