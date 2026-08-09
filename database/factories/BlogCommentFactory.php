<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogComment>
 */
class BlogCommentFactory extends Factory
{
    protected $model = BlogComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'status' => PublishStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
        ]);
    }
}
