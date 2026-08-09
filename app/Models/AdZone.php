<?php

namespace App\Models;

use App\Enums\AdZoneStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\AdZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'location', 'description', 'width', 'height', 'max_creatives', 'status', 'metadata'])]
class AdZone extends Model
{
    /** @use HasFactory<AdZoneFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return HasMany<AdCreative, $this>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    /**
     * @return HasMany<AdPricingSetting, $this>
     */
    public function pricingSettings(): HasMany
    {
        return $this->hasMany(AdPricingSetting::class);
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

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdZoneStatus::class,
            'metadata' => 'array',
        ];
    }
}
