<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionPost>
 */
class DiscussionPostFactory extends Factory
{
    protected $model = DiscussionPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discussion_thread_id' => DiscussionThread::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'status' => CommunityContentStatus::Pending,
            'upvotes_count' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunityContentStatus::Approved,
        ]);
    }
}
