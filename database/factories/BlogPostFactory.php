<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'blog_category_id' => BlogCategory::factory(),
            'author_id' => User::factory(),
            'featured_image_media_id' => MediaAsset::factory(),
            'title' => $title,
            'excerpt' => fake()->sentence(18),
            'content' => fake()->paragraphs(8, true),
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'is_featured' => false,
            'rejection_reason' => null,
            'seo_title' => $title,
            'seo_description' => fake()->sentence(18),
            'schema' => [
                '@type' => 'BlogPosting',
            ],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => [
            'is_featured' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Submitted,
            'published_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Approved,
            'published_at' => null,
        ]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::ChangesRequested,
            'published_at' => null,
        ]);
    }
}
