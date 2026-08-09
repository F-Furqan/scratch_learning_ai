<?php

namespace Database\Factories;

use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\RevenueShareRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueShareRule>
 */
class RevenueShareRuleFactory extends Factory
{
    protected $model = RevenueShareRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instructor_profile_id' => null,
            'course_id' => Course::factory(),
            'payment_product_id' => null,
            'type' => RevenueShareRuleType::Instructor,
            'status' => RevenueShareRuleStatus::Active,
            'share_percent' => fake()->randomFloat(2, 10, 50),
            'fixed_amount_cents' => null,
            'currency' => 'USD',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => fake()->optional()->sentence(10),
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
