<?php

namespace Database\Factories;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdImpression;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdImpression>
 */
class AdImpressionFactory extends Factory
{
    protected $model = AdImpression::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_zone_id' => AdZone::factory(),
            'ad_campaign_id' => AdCampaign::factory(),
            'ad_creative_id' => AdCreative::factory(),
            'session_id' => fake()->uuid(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'url' => fake()->url(),
            'referrer' => fake()->optional()->url(),
            'occurred_at' => now(),
            'metadata' => ['factory' => true],
        ];
    }
}
