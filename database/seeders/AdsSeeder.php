<?php

namespace Database\Seeders;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPricingModel;
use App\Enums\AdvertiserRequestStatus;
use App\Enums\AdZoneStatus;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPricingSetting;
use App\Models\AdvertiserRequest;
use App\Models\AdZone;
use App\Models\MediaAsset;
use Illuminate\Database\Seeder;

class AdsSeeder extends Seeder
{
    /**
     * Seed starter ad operations data for local and demo environments.
     */
    public function run(): void
    {
        $zone = AdZone::query()->firstOrCreate(
            ['slug' => 'homepage-hero-sponsor'],
            [
                'name' => 'Homepage Hero Sponsor',
                'location' => 'home.hero',
                'description' => 'Primary sponsor placement on the public home page.',
                'width' => 970,
                'height' => 250,
                'max_creatives' => 2,
                'status' => AdZoneStatus::Active,
                'metadata' => ['seeded' => true],
            ],
        );

        AdPricingSetting::query()->firstOrCreate(
            ['name' => 'Homepage Hero CPM'],
            [
                'ad_zone_id' => $zone->id,
                'pricing_model' => AdPricingModel::Cpm,
                'currency' => 'USD',
                'cpm_rate' => 18.00,
                'cpc_rate' => 1.50,
                'flat_rate' => 250.00,
                'min_spend' => 500.00,
                'is_active' => true,
                'effective_from' => now()->subMonth(),
                'notes' => 'Starter industrial-friendly ad pricing.',
            ],
        );

        $campaign = AdCampaign::query()->firstOrCreate(
            ['name' => 'Industrial Tools Awareness'],
            [
                'advertiser_name' => 'Acme Industrial Tools',
                'advertiser_email' => 'ads@example.com',
                'status' => AdCampaignStatus::Active,
                'pricing_model' => AdPricingModel::Cpm,
                'currency' => 'USD',
                'budget_total' => 5000,
                'daily_budget' => 250,
                'cpm_rate' => 18,
                'target_url' => 'https://example.com/industrial-tools',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'notes' => 'Seeded campaign for reporting workflows.',
                'metadata' => ['seeded' => true],
            ],
        );

        $media = MediaAsset::query()->first();

        AdCreative::query()->firstOrCreate(
            ['ad_campaign_id' => $campaign->id, 'name' => 'Hero Sponsor Creative'],
            [
                'ad_zone_id' => $zone->id,
                'media_asset_id' => $media?->id,
                'type' => AdCreativeType::Image,
                'status' => AdCreativeStatus::Active,
                'headline' => 'Industrial tools for reliable teams',
                'body' => 'Equip your team with practical systems and durable workflows.',
                'cta_text' => 'Learn more',
                'target_url' => 'https://example.com/industrial-tools',
                'weight' => 100,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'metadata' => ['seeded' => true],
            ],
        );

        AdvertiserRequest::query()->firstOrCreate(
            ['email' => 'partnerships@example.com'],
            [
                'requested_ad_zone_id' => $zone->id,
                'company_name' => 'Example Partnerships',
                'contact_name' => 'Partnership Manager',
                'website_url' => 'https://example.com',
                'budget_min' => 1000,
                'budget_max' => 7500,
                'message' => 'Interested in sponsoring practical engineering courses.',
                'status' => AdvertiserRequestStatus::Pending,
                'metadata' => ['seeded' => true],
            ],
        );
    }
}
