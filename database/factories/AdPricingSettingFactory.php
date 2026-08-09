<?php

namespace Database\Factories;

use App\Enums\AdPricingModel;
use App\Models\AdPricingSetting;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdPricingSetting>
 */
class AdPricingSettingFactory extends Factory
{
    protected $model = AdPricingSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_zone_id' => AdZone::factory(),
            'name' => fake()->unique()->sentence(3),
            'pricing_model' => AdPricingModel::Cpm,
            'currency' => 'USD',
            'cpm_rate' => 10.00,
            'cpc_rate' => 1.00,
            'flat_rate' => 150.00,
            'min_spend' => 250.00,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
            'effective_until' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
