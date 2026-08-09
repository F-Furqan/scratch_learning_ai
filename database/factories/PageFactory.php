<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'author_id' => User::factory(),
            'title' => $title,
            'excerpt' => fake()->sentence(18),
            'content' => fake()->paragraphs(5, true),
            'template' => fake()->randomElement(['default', 'legal', 'landing']),
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'sort_order' => fake()->numberBetween(1, 50),
            'seo_title' => $title,
            'seo_description' => fake()->sentence(18),
            'schema' => [
                '@type' => 'WebPage',
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
}
