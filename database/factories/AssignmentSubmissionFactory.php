<?php

namespace Database\Factories;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'media_asset_id' => null,
            'graded_by' => null,
            'status' => AssignmentSubmissionStatus::Submitted,
            'submitted_text' => fake()->paragraph(),
            'score' => null,
            'passed' => null,
            'feedback' => null,
            'submitted_at' => now(),
            'graded_at' => null,
        ];
    }

    public function withMedia(): static
    {
        return $this->state(fn (): array => [
            'media_asset_id' => MediaAsset::factory(),
        ]);
    }
}
