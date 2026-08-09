<?php

namespace Database\Factories;

use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseCategory>
 */
class CourseCategoryFactory extends Factory
{
    protected $model = CourseCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'name' => $name,
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
            'seo_title' => $name.' Courses',
            'seo_description' => fake()->sentence(12),
            'schema' => [
                '@type' => 'CollectionPage',
            ],
        ];
    }
}
