<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Models\DiscussionForum;
use App\Models\DiscussionThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionThread>
 */
class DiscussionThreadFactory extends Factory
{
    protected $model = DiscussionThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'discussion_forum_id' => DiscussionForum::factory(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'body' => fake()->paragraphs(2, true),
            'status' => CommunityContentStatus::Pending,
            'is_pinned' => false,
            'is_locked' => false,
            'replies_count' => 0,
            'upvotes_count' => 0,
            'last_activity_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunityContentStatus::Approved,
        ]);
    }
}
