<?php

namespace App\Models;

use Database\Factories\HomeHeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'media_asset_id',
    'eyebrow',
    'title',
    'subtitle',
    'button_label',
    'target_url',
    'image_url',
    'image_alt',
    'text_position',
    'sort_order',
    'is_active',
    'opens_in_new_tab',
    'metadata',
])]
class HomeHeroSlide extends Model
{
    /** @use HasFactory<HomeHeroSlideFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opens_in_new_tab' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
