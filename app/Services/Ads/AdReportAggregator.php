<?php

namespace App\Services\Ads;

use App\Enums\AdPricingModel;
use App\Models\AdCampaign;
use App\Models\AdCampaignReport;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\AdPricingSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdReportAggregator
{
    public function aggregate(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $fromDate = Carbon::parse($from ?? now()->subDay())->startOfDay();
        $toDate = Carbon::parse($to ?? now())->endOfDay();

        $impressions = $this->impressionGroups($fromDate, $toDate);
        $clicks = $this->clickGroups($fromDate, $toDate);
        $keys = $impressions->keys()->merge($clicks->keys())->unique();
        $generated = 0;

        foreach ($keys as $key) {
            $impressionRow = $impressions->get($key);
            $clickRow = $clicks->get($key);
            $dimensions = $this->dimensionsFromKey((string) $key);
            $impressionCount = (int) ($impressionRow['impressions'] ?? 0);
            $clickCount = (int) ($clickRow['clicks'] ?? 0);
            $pricing = $this->pricingFor((int) $dimensions['campaign_id'], (int) $dimensions['zone_id']);
            $revenue = $this->revenue($pricing, $impressionCount, $clickCount);

            AdCampaignReport::query()->updateOrCreate(
                [
                    'report_date' => $dimensions['date'],
                    'ad_zone_id' => $dimensions['zone_id'],
                    'ad_campaign_id' => $dimensions['campaign_id'],
                    'ad_creative_id' => $dimensions['creative_id'],
                ],
                [
                    'impressions' => $impressionCount,
                    'clicks' => $clickCount,
                    'ctr' => $impressionCount > 0 ? round(($clickCount / $impressionCount) * 100, 4) : 0,
                    'revenue' => $revenue,
                    'spend' => $revenue,
                    'effective_cpm' => $impressionCount > 0 ? round($revenue / ($impressionCount / 1000), 4) : 0,
                    'effective_cpc' => $clickCount > 0 ? round($revenue / $clickCount, 4) : 0,
                    'currency' => $pricing['currency'],
                    'generated_at' => now(),
                ],
            );

            $generated++;
        }

        return $generated;
    }

    /**
     * @return Collection<string, array{impressions: int}>
     */
    private function impressionGroups(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $dateExpression = DB::getDriverName() === 'sqlite'
            ? DB::raw('date(occurred_at) as report_date')
            : DB::raw('DATE(occurred_at) as report_date');

        return DB::table((new AdImpression)->getTable())
            ->select([
                $dateExpression,
                'ad_zone_id',
                'ad_campaign_id',
                'ad_creative_id',
                DB::raw('count(*) as impressions'),
            ])
            ->whereBetween('occurred_at', [$from, $to])
            ->groupBy('report_date', 'ad_zone_id', 'ad_campaign_id', 'ad_creative_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->key((string) data_get($row, 'report_date'), (int) data_get($row, 'ad_zone_id'), (int) data_get($row, 'ad_campaign_id'), (int) data_get($row, 'ad_creative_id')) => [
                    'impressions' => (int) data_get($row, 'impressions'),
                ],
            ]);
    }

    /**
     * @return Collection<string, array{clicks: int}>
     */
    private function clickGroups(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $dateExpression = DB::getDriverName() === 'sqlite'
            ? DB::raw('date(occurred_at) as report_date')
            : DB::raw('DATE(occurred_at) as report_date');

        return DB::table((new AdClick)->getTable())
            ->select([
                $dateExpression,
                'ad_zone_id',
                'ad_campaign_id',
                'ad_creative_id',
                DB::raw('count(*) as clicks'),
            ])
            ->whereBetween('occurred_at', [$from, $to])
            ->groupBy('report_date', 'ad_zone_id', 'ad_campaign_id', 'ad_creative_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->key((string) data_get($row, 'report_date'), (int) data_get($row, 'ad_zone_id'), (int) data_get($row, 'ad_campaign_id'), (int) data_get($row, 'ad_creative_id')) => [
                    'clicks' => (int) data_get($row, 'clicks'),
                ],
            ]);
    }

    private function key(string $date, int $zoneId, int $campaignId, int $creativeId): string
    {
        return implode('|', [$date, $zoneId, $campaignId, $creativeId]);
    }

    /**
     * @return array{date: string, zone_id: int, campaign_id: int, creative_id: int}
     */
    private function dimensionsFromKey(string $key): array
    {
        [$date, $zoneId, $campaignId, $creativeId] = explode('|', $key);

        return [
            'date' => $date,
            'zone_id' => (int) $zoneId,
            'campaign_id' => (int) $campaignId,
            'creative_id' => (int) $creativeId,
        ];
    }

    /**
     * @return array{model: AdPricingModel, currency: string, cpm: float, cpc: float, flat: float}
     */
    private function pricingFor(int $campaignId, int $zoneId): array
    {
        $campaign = AdCampaign::query()->find($campaignId);
        $setting = AdPricingSetting::query()
            ->where('is_active', true)
            ->where(function ($query) use ($zoneId): void {
                $query->whereNull('ad_zone_id')->orWhere('ad_zone_id', $zoneId);
            })
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', now());
            })
            ->orderByRaw('case when ad_zone_id is null then 1 else 0 end')
            ->latest()
            ->first();

        $model = $campaign?->getAttribute('pricing_model') ?: $setting?->getAttribute('pricing_model') ?: AdPricingModel::Cpm;

        if (! $model instanceof AdPricingModel) {
            $model = AdPricingModel::tryFrom((string) $model) ?: AdPricingModel::Cpm;
        }

        return [
            'model' => $model,
            'currency' => (string) ($campaign?->getAttribute('currency') ?: $setting?->getAttribute('currency') ?: 'USD'),
            'cpm' => (float) ($campaign?->getAttribute('cpm_rate') ?: $setting?->getAttribute('cpm_rate') ?: 0),
            'cpc' => (float) ($campaign?->getAttribute('cpc_rate') ?: $setting?->getAttribute('cpc_rate') ?: 0),
            'flat' => (float) ($campaign?->getAttribute('flat_rate') ?: $setting?->getAttribute('flat_rate') ?: 0),
        ];
    }

    /**
     * @param  array{model: AdPricingModel, currency: string, cpm: float, cpc: float, flat: float}  $pricing
     */
    private function revenue(array $pricing, int $impressions, int $clicks): float
    {
        return match ($pricing['model']) {
            AdPricingModel::Cpc => round($clicks * $pricing['cpc'], 4),
            AdPricingModel::Flat => round($pricing['flat'], 4),
            AdPricingModel::Cpm => round(($impressions / 1000) * $pricing['cpm'], 4),
        };
    }
}
