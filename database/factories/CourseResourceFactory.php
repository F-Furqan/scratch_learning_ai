<?php

namespace Database\Factories;

use App\Enums\LearningResourceAccess;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseResource>
 */
class CourseResourceFactory extends Factory
{
    protected $model = CourseResource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(10),
            'type' => 'download',
            'access_level' => LearningResourceAccess::Enrolled,
            'file_path' => null,
            'external_url' => 'https://resources.example.com/'.fake()->uuid(),
            'is_downloadable' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
