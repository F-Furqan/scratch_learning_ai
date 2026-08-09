<?php

namespace App\Models;

use App\Enums\GrowthStatus;
use Database\Factories\SocialShareImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['shareable_type', 'shareable_id', 'media_asset_id', 'image_url', 'title', 'alt_text', 'template', 'status', 'metadata'])]
class SocialShareImage extends Model
{
    /** @use HasFactory<SocialShareImageFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GrowthStatus::class,
            'metadata' => 'array',
        ];
    }
}
