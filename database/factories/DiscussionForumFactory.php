<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\DiscussionForum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionForum>
 */
class DiscussionForumFactory extends Factory
{
    protected $model = DiscussionForum::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'course_id' => Course::factory(),
            'course_category_id' => null,
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'description' => fake()->paragraph(),
            'visibility' => CommunityVisibility::Public,
            'status' => CommunityContentStatus::Approved,
            'threads_count' => 0,
            'posts_count' => 0,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    public function category(): static
    {
        return $this->state(fn (): array => [
            'course_id' => null,
            'course_category_id' => CourseCategory::factory(),
        ]);
    }
}
