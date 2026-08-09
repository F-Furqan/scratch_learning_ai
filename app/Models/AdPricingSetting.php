<?php

namespace App\Models;

use App\Enums\AdPricingModel;
use Database\Factories\AdPricingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ad_zone_id',
    'name',
    'pricing_model',
    'currency',
    'cpm_rate',
    'cpc_rate',
    'flat_rate',
    'min_spend',
    'is_active',
    'effective_from',
    'effective_until',
    'notes',
])]
class AdPricingSetting extends Model
{
    /** @use HasFactory<AdPricingSettingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AdZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'ad_zone_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_model' => AdPricingModel::class,
            'cpm_rate' => 'decimal:4',
            'cpc_rate' => 'decimal:4',
            'flat_rate' => 'decimal:4',
            'min_spend' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }
}
