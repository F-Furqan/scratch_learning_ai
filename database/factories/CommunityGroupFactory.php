<?php

namespace Database\Factories;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\CommunityGroup;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityGroup>
 */
class CommunityGroupFactory extends Factory
{
    protected $model = CommunityGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'course_id' => Course::factory(),
            'created_by' => User::factory(),
            'name' => str($name)->headline()->toString(),
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->paragraph(),
            'visibility' => CommunityVisibility::PaidMembers,
            'status' => CommunityContentStatus::Approved,
            'requires_paid_access' => true,
            'members_count' => 0,
            'metadata' => null,
        ];
    }
}
