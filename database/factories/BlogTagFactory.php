<?php

namespace Database\Factories;

use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogTag>
 */
class BlogTagFactory extends Factory
{
    protected $model = BlogTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
