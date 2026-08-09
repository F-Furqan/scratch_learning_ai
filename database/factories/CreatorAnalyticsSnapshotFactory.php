<?php

namespace Database\Factories;

use App\Models\CreatorAnalyticsSnapshot;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatorAnalyticsSnapshot>
 */
class CreatorAnalyticsSnapshotFactory extends Factory
{
    protected $model = CreatorAnalyticsSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instructor_profile_id' => null,
            'period_date' => today(),
            'period' => 'daily',
            'blog_views' => fake()->numberBetween(0, 1000),
            'course_views' => fake()->numberBetween(0, 1000),
            'lesson_views' => fake()->numberBetween(0, 1000),
            'comments_count' => fake()->numberBetween(0, 50),
            'enrollments_count' => fake()->numberBetween(0, 50),
            'course_revenue_cents' => fake()->numberBetween(0, 100_000),
            'ad_revenue_cents' => fake()->numberBetween(0, 10_000),
            'engagement_score' => fake()->numberBetween(0, 100),
            'metadata' => [],
        ];
    }

    public function forInstructor(InstructorProfile $profile): static
    {
        return $this->state(fn (): array => [
            'user_id' => $profile->user_id,
            'instructor_profile_id' => $profile->id,
        ]);
    }
}
