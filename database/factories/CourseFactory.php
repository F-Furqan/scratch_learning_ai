<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'course_category_id' => CourseCategory::factory(),
            'course_subcategory_id' => null,
            'created_by' => User::factory(),
            'thumbnail_media_id' => MediaAsset::factory(),
            'ownership_video_media_id' => null,
            'ownership_video_url' => 'https://videos.example.com/ownership/'.fake()->uuid(),
            'ownership_statement' => 'I confirm this course was created by me and I have rights to publish it on Scratch Learning.',
            'ownership_confirmed_at' => now(),
            'title' => $title,
            'short_description' => fake()->sentence(14),
            'description' => fake()->paragraphs(4, true),
            'intro_video_url' => fake()->optional()->url(),
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'language' => 'en',
            'price' => fake()->randomFloat(2, 19, 299),
            'is_free' => false,
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'seo_title' => $title,
            'seo_description' => fake()->sentence(18),
            'schema' => [
                '@type' => 'Course',
            ],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'price' => 0,
            'is_free' => true,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function withoutOwnershipProof(): static
    {
        return $this->state(fn (): array => [
            'ownership_video_media_id' => null,
            'ownership_video_url' => null,
            'ownership_statement' => null,
            'ownership_confirmed_at' => null,
        ]);
    }
}
