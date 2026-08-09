<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'name' => $name,
            'description' => fake()->paragraph(),
            'is_active' => true,
            'seo_title' => $name,
            'seo_description' => fake()->sentence(14),
            'schema' => [
                '@type' => 'Blog',
            ],
        ];
    }
}
