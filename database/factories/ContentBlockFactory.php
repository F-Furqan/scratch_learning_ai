<?php

namespace Database\Factories;

use App\Models\ContentBlock;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentBlock>
 */
class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'key' => fake()->unique()->slug(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'settings' => [
                'width' => 'contained',
            ],
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
