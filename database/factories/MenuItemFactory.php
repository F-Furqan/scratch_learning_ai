<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'page_id' => null,
            'title' => fake()->words(2, true),
            'type' => 'url',
            'url' => '/'.fake()->slug(),
            'route_name' => null,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
