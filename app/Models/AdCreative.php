<?php

namespace App\Models;

use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use Carbon\CarbonInterface;
use Database\Factories\AdCreativeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ad_campaign_id',
    'ad_zone_id',
    'media_asset_id',
    'name',
    'type',
    'status',
    'headline',
    'body',
    'cta_text',
    'target_url',
    'html_snippet',
    'weight',
    'starts_at',
    'ends_at',
    'metadata',
])]
class AdCreative extends Model
{
    /** @use HasFactory<AdCreativeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    /**
     * @return BelongsTo<AdZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'ad_zone_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @return HasMany<AdImpression, $this>
     */
    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }

    /**
     * @return HasMany<AdClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class);
    }

    public function destinationUrl(): ?string
    {
        return $this->target_url ?: $this->campaign?->target_url;
    }

    public function isActive(): bool
    {
        $status = $this->getAttribute('status');
        $startsAt = $this->getAttribute('starts_at');
        $endsAt = $this->getAttribute('ends_at');

        return $status instanceof AdCreativeStatus
            && $status === AdCreativeStatus::Active
            && (! $startsAt instanceof CarbonInterface || $startsAt->isPast())
            && (! $endsAt instanceof CarbonInterface || $endsAt->isFuture())
            && ($this->campaign === null || $this->campaign->isActive());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdCreativeType::class,
            'status' => AdCreativeStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
