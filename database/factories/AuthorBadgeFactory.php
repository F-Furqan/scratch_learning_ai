<?php

namespace Database\Factories;

use App\Models\AuthorBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorBadge>
 */
class AuthorBadgeFactory extends Factory
{
    protected $model = AuthorBadge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(12),
            'icon' => 'badge-check',
            'color' => fake()->randomElement(['teal', 'indigo', 'amber', 'slate']),
            'marks_verified_expert' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function verifiedExpert(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Verified Expert',
            'marks_verified_expert' => true,
            'color' => 'teal',
        ]);
    }
}
