<?php

namespace App\Models;

use App\Enums\GrowthStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\NewsletterCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lead_magnet_id', 'name', 'slug', 'subject', 'audience', 'status', 'scheduled_at', 'sent_at', 'metadata'])]
class NewsletterCampaign extends Model
{
    /** @use HasFactory<NewsletterCampaignFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<LeadMagnet, $this>
     */
    public function leadMagnet(): BelongsTo
    {
        return $this->belongsTo(LeadMagnet::class);
    }

    /**
     * @return HasMany<LeadSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(LeadSubmission::class);
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'status' => GrowthStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
