<?php

namespace Database\Factories;

use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdZone;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCreative>
 */
class AdCreativeFactory extends Factory
{
    protected $model = AdCreative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_campaign_id' => AdCampaign::factory(),
            'ad_zone_id' => AdZone::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'name' => fake()->unique()->sentence(3),
            'type' => AdCreativeType::Image,
            'status' => AdCreativeStatus::Draft,
            'headline' => fake()->sentence(6),
            'body' => fake()->sentence(14),
            'cta_text' => fake()->randomElement(['Start now', 'Learn more', 'Book demo']),
            'target_url' => fake()->url(),
            'html_snippet' => null,
            'weight' => 100,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'metadata' => ['factory' => true],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AdCreativeStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
