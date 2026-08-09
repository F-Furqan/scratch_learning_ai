<?php

namespace Database\Factories;

use App\Models\AdCampaign;
use App\Models\AdCampaignReport;
use App\Models\AdCreative;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCampaignReport>
 */
class AdCampaignReportFactory extends Factory
{
    protected $model = AdCampaignReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $impressions = fake()->numberBetween(100, 10000);
        $clicks = fake()->numberBetween(1, 500);

        return [
            'report_date' => now()->toDateString(),
            'ad_zone_id' => AdZone::factory(),
            'ad_campaign_id' => AdCampaign::factory(),
            'ad_creative_id' => AdCreative::factory(),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => round(($clicks / $impressions) * 100, 4),
            'revenue' => 125.00,
            'spend' => 125.00,
            'effective_cpm' => 12.50,
            'effective_cpc' => 1.25,
            'currency' => 'USD',
            'generated_at' => now(),
        ];
    }
}
