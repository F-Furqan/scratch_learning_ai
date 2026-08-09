<?php

namespace Database\Factories;

use App\Enums\BloggerStatus;
use App\Models\BloggerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BloggerProfile>
 */
class BloggerProfileFactory extends Factory
{
    protected $model = BloggerProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => fake()->optional()->phoneNumber(),
            'bio' => fake()->paragraph(),
            'expertise' => fake()->randomElement(['Laravel', 'Vue', 'DevOps', 'Product']),
            'linkedin_url' => 'https://www.linkedin.com/in/'.fake()->userName(),
            'website_url' => fake()->optional()->url(),
            'application_reason' => fake()->paragraph(),
            'status' => BloggerStatus::Pending,
            'is_verified_creator' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => BloggerStatus::Approved,
            'is_verified_creator' => true,
            'reviewed_at' => now(),
        ]);
    }
}
