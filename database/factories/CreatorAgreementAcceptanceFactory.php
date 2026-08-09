<?php

namespace Database\Factories;

use App\Models\CreatorAgreementAcceptance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatorAgreementAcceptance>
 */
class CreatorAgreementAcceptanceFactory extends Factory
{
    protected $model = CreatorAgreementAcceptance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'terms_version' => config('platform.creator_agreement.version'),
            'accepted_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
