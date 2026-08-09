<?php

namespace Database\Factories;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityGroupMember>
 */
class CommunityGroupMemberFactory extends Factory
{
    protected $model = CommunityGroupMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_group_id' => CommunityGroup::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
