<?php

namespace App\Models;

use App\Enums\AdCampaignStatus;
use App\Enums\AdPricingModel;
use Carbon\CarbonInterface;
use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'advertiser_name',
    'advertiser_email',
    'status',
    'pricing_model',
    'currency',
    'budget_total',
    'daily_budget',
    'cpm_rate',
    'cpc_rate',
    'flat_rate',
    'target_url',
    'starts_at',
    'ends_at',
    'notes',
    'metadata',
])]
class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use HasFactory;

    /**
     * @return HasMany<AdCreative, $this>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    /**
     * @return HasMany<AdCampaignReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(AdCampaignReport::class);
    }

    public function isActive(): bool
    {
        $status = $this->getAttribute('status');
        $startsAt = $this->getAttribute('starts_at');
        $endsAt = $this->getAttribute('ends_at');

        return $status instanceof AdCampaignStatus
            && $status === AdCampaignStatus::Active
            && (! $startsAt instanceof CarbonInterface || $startsAt->isPast())
            && (! $endsAt instanceof CarbonInterface || $endsAt->isFuture());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdCampaignStatus::class,
            'pricing_model' => AdPricingModel::class,
            'budget_total' => 'decimal:2',
            'daily_budget' => 'decimal:2',
            'cpm_rate' => 'decimal:4',
            'cpc_rate' => 'decimal:4',
            'flat_rate' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
