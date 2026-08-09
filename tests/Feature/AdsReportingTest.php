<?php

namespace Tests\Feature;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPricingModel;
use App\Enums\AdZoneStatus;
use App\Enums\RoleName;
use App\Models\AdCampaign;
use App\Models\AdCampaignReport;
use App\Models\AdClick;
use App\Models\AdCreative;
use App\Models\AdImpression;
use App\Models\AdZone;
use App\Models\User;
use App\Services\Ads\AdReportAggregator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdsReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_admin_can_manage_ad_zone_campaign_and_creative(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.ads.zones.store'), [
                'name' => 'Course Sidebar',
                'location' => 'course.sidebar',
                'description' => 'Course detail sidebar sponsor.',
                'width' => 300,
                'height' => 250,
                'max_creatives' => 2,
                'status' => AdZoneStatus::Active->value,
            ])
            ->assertRedirect();

        $zone = AdZone::query()->where('name', 'Course Sidebar')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.ads.campaigns.store'), [
                'name' => 'Ops Tooling Launch',
                'advertiser_name' => 'Ops Tools Inc',
                'advertiser_email' => 'ads@ops.example',
                'status' => AdCampaignStatus::Active->value,
                'pricing_model' => AdPricingModel::Cpm->value,
                'currency' => 'USD',
                'budget_total' => 3000,
                'daily_budget' => 150,
                'cpm_rate' => 20,
                'target_url' => 'https://example.com/ops',
                'starts_at' => now()->subDay()->toDateTimeString(),
                'ends_at' => now()->addMonth()->toDateTimeString(),
            ])
            ->assertRedirect();

        $campaign = AdCampaign::query()->where('name', 'Ops Tooling Launch')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.ads.creatives.store'), [
                'ad_campaign_id' => $campaign->id,
                'ad_zone_id' => $zone->id,
                'name' => 'Sidebar Creative',
                'type' => AdCreativeType::Text->value,
                'status' => AdCreativeStatus::Active->value,
                'headline' => 'Scale your operations',
                'body' => 'Tools for reliable learning teams.',
                'cta_text' => 'Learn more',
                'target_url' => 'https://example.com/ops',
                'weight' => 100,
                'starts_at' => now()->subDay()->toDateTimeString(),
                'ends_at' => now()->addMonth()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(AdCreative::class, [
            'name' => 'Sidebar Creative',
            'ad_campaign_id' => $campaign->id,
            'ad_zone_id' => $zone->id,
        ]);
    }

    public function test_public_ad_tracking_records_impressions_and_clicks(): void
    {
        $zone = AdZone::factory()->active()->create();
        $campaign = AdCampaign::factory()->active()->create([
            'target_url' => 'https://advertiser.example/landing',
        ]);
        $creative = AdCreative::factory()->active()->create([
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
            'target_url' => 'https://advertiser.example/creative',
        ]);

        $this->postJson(route('api.v1.ads.impressions.store'), [
            'creative_id' => $creative->id,
            'zone_id' => $zone->id,
            'url' => 'https://learning.example/courses',
            'metadata' => ['placement' => 'course-sidebar'],
        ])->assertOk()
            ->assertJsonPath('data.tracked', true);

        $this->get(route('api.v1.ads.clicks.store', $creative))
            ->assertRedirect('https://advertiser.example/creative');

        $this->assertDatabaseHas(AdImpression::class, [
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative->id,
        ]);
        $this->assertDatabaseHas(AdClick::class, [
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative->id,
            'target_url' => 'https://advertiser.example/creative',
        ]);
    }

    public function test_aggregation_job_creates_daily_campaign_reports(): void
    {
        $zone = AdZone::factory()->active()->create();
        $campaign = AdCampaign::factory()->active()->create([
            'pricing_model' => AdPricingModel::Cpm,
            'cpm_rate' => 20,
            'currency' => 'USD',
        ]);
        $creative = AdCreative::factory()->active()->create([
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
        ]);

        AdImpression::factory()->count(100)->create([
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative->id,
            'occurred_at' => now()->subDay(),
        ]);
        AdClick::factory()->count(5)->create([
            'ad_zone_id' => $zone->id,
            'ad_campaign_id' => $campaign->id,
            'ad_creative_id' => $creative->id,
            'occurred_at' => now()->subDay(),
        ]);

        $generated = app(AdReportAggregator::class)->aggregate(now()->subDays(2), now());

        $this->assertSame(1, $generated);

        $report = AdCampaignReport::query()->firstOrFail();

        $this->assertSame(100, $report->impressions);
        $this->assertSame(5, $report->clicks);
        $this->assertSame('5.0000', $report->ctr);
        $this->assertSame('2.0000', $report->revenue);
    }

    public function test_admin_reports_screen_shows_revenue_engagement_and_content_metrics(): void
    {
        $campaign = AdCampaign::factory()->active()->create();
        $zone = AdZone::factory()->active()->create();
        $creative = AdCreative::factory()->active()->create([
            'ad_campaign_id' => $campaign->id,
            'ad_zone_id' => $zone->id,
        ]);

        AdCampaignReport::factory()->create([
            'ad_campaign_id' => $campaign->id,
            'ad_zone_id' => $zone->id,
            'ad_creative_id' => $creative->id,
            'revenue' => 125,
            'spend' => 125,
            'report_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Reports')
                ->where('reports.revenue.total', 125)
                ->where('reports.revenue.activeCampaigns', 1)
                ->has('reports.engagement')
                ->has('reports.content')
                ->has('reports.topCampaigns', 1),
            );
    }
}
