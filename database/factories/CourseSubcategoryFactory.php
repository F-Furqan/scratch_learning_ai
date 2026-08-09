<?php

namespace Database\Factories;

use App\Models\CourseCategory;
use App\Models\CourseSubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSubcategory>
 */
class CourseSubcategoryFactory extends Factory
{
    protected $model = CourseSubcategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'course_category_id' => CourseCategory::factory(),
            'name' => $name,
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
            'seo_title' => $name.' Training',
            'seo_description' => fake()->sentence(12),
            'schema' => [
                '@type' => 'CollectionPage',
            ],
        ];
    }
}
