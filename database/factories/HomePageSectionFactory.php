<?php

namespace Database\Factories;

use App\Enums\HomePageSectionType;
use App\Models\HomePageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomePageSection>
 */
class HomePageSectionFactory extends Factory
{
    protected $model = HomePageSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(HomePageSectionType::cases());

        return [
            'key' => fake()->unique()->slug(3),
            'type' => $type,
            'eyebrow' => fake()->words(2, true),
            'title' => fake()->sentence(5),
            'subtitle' => fake()->sentence(12),
            'body' => fake()->paragraph(),
            'cta_label' => 'Explore',
            'cta_url' => '/courses',
            'background' => fake()->randomElement(['white', 'soft', 'dark']),
            'sort_order' => fake()->numberBetween(10, 100),
            'is_active' => true,
            'payload' => [],
        ];
    }
}
