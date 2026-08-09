<?php

namespace Database\Factories;

use App\Enums\AffiliateStatus;
use App\Models\AffiliatePartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliatePartner>
 */
class AffiliatePartnerFactory extends Factory
{
    protected $model = AffiliatePartner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('PARTNER??##')),
            'status' => AffiliateStatus::Active,
            'commission_rate_basis_points' => 1000,
            'cookie_days' => 30,
            'payout_email' => fake()->safeEmail(),
            'notes' => null,
            'metadata' => [],
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }
}
