<?php

namespace Database\Factories;

use App\Models\EditorialRevision;
use App\Models\ReviewerComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewerComment>
 */
class ReviewerCommentFactory extends Factory
{
    protected $model = ReviewerComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'editorial_revision_id' => EditorialRevision::factory(),
            'reviewer_id' => User::factory(),
            'resolved_by' => null,
            'field_path' => fake()->optional()->randomElement(['title', 'content', 'seo_description']),
            'body' => fake()->sentence(14),
            'is_resolved' => false,
            'resolved_at' => null,
        ];
    }
}
