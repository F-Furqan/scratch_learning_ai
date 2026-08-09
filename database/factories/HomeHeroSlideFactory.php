<?php

namespace Database\Factories;

use App\Models\HomeHeroSlide;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeHeroSlide>
 */
class HomeHeroSlideFactory extends Factory
{
    protected $model = HomeHeroSlide::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'eyebrow' => fake()->sentence(3),
            'title' => fake()->sentence(6),
            'subtitle' => fake()->sentence(14),
            'button_label' => fake()->randomElement(['Explore courses', 'Start learning', 'View curriculum']),
            'target_url' => '/courses',
            'image_url' => null,
            'image_alt' => fake()->sentence(5),
            'text_position' => fake()->randomElement(['left', 'center', 'right']),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
            'opens_in_new_tab' => false,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
