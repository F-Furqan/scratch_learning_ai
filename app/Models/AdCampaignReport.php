<?php

namespace App\Models;

use Database\Factories\AdCampaignReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_date',
    'ad_zone_id',
    'ad_campaign_id',
    'ad_creative_id',
    'impressions',
    'clicks',
    'ctr',
    'revenue',
    'spend',
    'effective_cpm',
    'effective_cpc',
    'currency',
    'generated_at',
])]
class AdCampaignReport extends Model
{
    /** @use HasFactory<AdCampaignReportFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AdZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'ad_zone_id');
    }

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    /**
     * @return BelongsTo<AdCreative, $this>
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(AdCreative::class, 'ad_creative_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'ctr' => 'decimal:4',
            'revenue' => 'decimal:4',
            'spend' => 'decimal:4',
            'effective_cpm' => 'decimal:4',
            'effective_cpc' => 'decimal:4',
            'generated_at' => 'datetime',
        ];
    }
}
