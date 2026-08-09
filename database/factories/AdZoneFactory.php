<?php

namespace Database\Factories;

use App\Enums\AdZoneStatus;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdZone>
 */
class AdZoneFactory extends Factory
{
    protected $model = AdZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'location' => fake()->randomElement(['home.hero', 'course.sidebar', 'blog.inline', 'footer.banner']),
            'description' => fake()->sentence(),
            'width' => fake()->randomElement([300, 728, 970]),
            'height' => fake()->randomElement([90, 250, 320]),
            'max_creatives' => fake()->numberBetween(1, 4),
            'status' => AdZoneStatus::Active,
            'metadata' => ['factory' => true],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AdZoneStatus::Active,
        ]);
    }
}
