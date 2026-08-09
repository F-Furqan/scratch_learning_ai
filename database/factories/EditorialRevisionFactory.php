<?php

namespace Database\Factories;

use App\Enums\EditorialRevisionStatus;
use App\Models\BlogPost;
use App\Models\EditorialRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialRevision>
 */
class EditorialRevisionFactory extends Factory
{
    protected $model = EditorialRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post = BlogPost::factory();

        return [
            'editorialable_type' => BlogPost::class,
            'editorialable_id' => $post,
            'author_id' => User::factory(),
            'reviewer_id' => null,
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(12),
            'payload' => ['title' => fake()->sentence(5)],
            'status' => EditorialRevisionStatus::Draft,
            'submitted_at' => null,
            'reviewed_at' => null,
            'scheduled_at' => null,
            'published_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => EditorialRevisionStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => EditorialRevisionStatus::ChangesRequested,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }
}
