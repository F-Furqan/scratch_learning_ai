<?php

namespace Database\Factories;

use App\Enums\TeamSeatStatus;
use App\Models\TeamAccount;
use App\Models\TeamSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSeat>
 */
class TeamSeatFactory extends Factory
{
    protected $model = TeamSeat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_account_id' => TeamAccount::factory(),
            'user_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'role' => 'member',
            'status' => TeamSeatStatus::Invited,
            'invited_at' => now(),
            'accepted_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => TeamSeatStatus::Active,
            'accepted_at' => now(),
        ]);
    }
}
