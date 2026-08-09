<?php

namespace App\Models;

use App\Enums\GrowthStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\LeadMagnetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_media_id', 'title', 'slug', 'description', 'status', 'form_headline', 'delivery_url', 'metadata'])]
class LeadMagnet extends Model
{
    /** @use HasFactory<LeadMagnetFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'asset_media_id');
    }

    /**
     * @return HasMany<LeadSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(LeadSubmission::class);
    }

    protected function casts(): array
    {
        return [
            'status' => GrowthStatus::class,
            'metadata' => 'array',
        ];
    }
}
