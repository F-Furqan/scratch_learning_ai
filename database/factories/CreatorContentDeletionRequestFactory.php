<?php

namespace Database\Factories;

use App\Enums\CreatorContentDeletionStatus;
use App\Models\BlogPost;
use App\Models\CreatorContentDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatorContentDeletionRequest>
 */
class CreatorContentDeletionRequestFactory extends Factory
{
    protected $model = CreatorContentDeletionRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post = BlogPost::factory();

        return [
            'requester_id' => User::factory(),
            'content_type' => BlogPost::class,
            'content_id' => $post,
            'status' => CreatorContentDeletionStatus::Pending,
            'reason' => fake()->sentence(),
            'decided_by' => null,
            'decided_at' => null,
            'admin_note' => null,
        ];
    }
}
