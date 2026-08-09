<?php

namespace Database\Factories;

use App\Enums\InstructorProfileStatus;
use App\Models\InstructorProfile;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorProfile>
 */
class InstructorProfileFactory extends Factory
{
    protected $model = InstructorProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'avatar_media_id' => MediaAsset::factory(),
            'reviewed_by' => null,
            'display_name' => fake()->name(),
            'headline' => fake()->sentence(6),
            'bio' => fake()->paragraphs(2, true),
            'credentials' => fake()->sentence(12),
            'expertise' => fake()->randomElement(['Laravel', 'DevOps', 'Data', 'Product Engineering']),
            'website_url' => fake()->optional()->url(),
            'linkedin_url' => fake()->optional()->url(),
            'status' => InstructorProfileStatus::Pending,
            'is_verified_expert' => false,
            'accepts_revenue_share' => true,
            'payout_currency' => 'USD',
            'payout_account_reference' => fake()->optional()->uuid(),
            'reviewed_at' => null,
            'admin_notes' => null,
            'metadata' => ['seeded' => true],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => InstructorProfileStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => InstructorProfileStatus::Approved,
            'is_verified_expert' => true,
            'reviewed_at' => now(),
        ]);
    }
}
