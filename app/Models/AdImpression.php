<?php

namespace App\Models;

use Database\Factories\AdImpressionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ad_zone_id',
    'ad_campaign_id',
    'ad_creative_id',
    'session_id',
    'ip_hash',
    'user_agent_hash',
    'url',
    'referrer',
    'occurred_at',
    'metadata',
])]
class AdImpression extends Model
{
    /** @use HasFactory<AdImpressionFactory> */
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
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
