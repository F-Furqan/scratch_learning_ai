<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\BlogFaq;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogFaq>
 */
class BlogFaqFactory extends Factory
{
    protected $model = BlogFaq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 20),
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
