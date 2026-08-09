<?php

namespace Database\Factories;

use App\Enums\AdCampaignStatus;
use App\Enums\AdPricingModel;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCampaign>
 */
class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'advertiser_name' => fake()->company(),
            'advertiser_email' => fake()->companyEmail(),
            'status' => AdCampaignStatus::Draft,
            'pricing_model' => AdPricingModel::Cpm,
            'currency' => 'USD',
            'budget_total' => fake()->randomFloat(2, 1000, 10000),
            'daily_budget' => fake()->randomFloat(2, 50, 500),
            'cpm_rate' => 12.50,
            'cpc_rate' => 1.25,
            'flat_rate' => 100.00,
            'target_url' => fake()->url(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'notes' => fake()->sentence(),
            'metadata' => ['factory' => true],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AdCampaignStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
