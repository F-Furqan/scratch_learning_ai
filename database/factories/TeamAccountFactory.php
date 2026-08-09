<?php

namespace Database\Factories;

use App\Enums\TeamAccountStatus;
use App\Models\TeamAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamAccount>
 */
class TeamAccountFactory extends Factory
{
    protected $model = TeamAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company().' Learning',
            'status' => TeamAccountStatus::Active,
            'seat_limit' => 5,
            'paddle_customer_id' => null,
            'paddle_subscription_id' => null,
            'metadata' => [],
        ];
    }
}
