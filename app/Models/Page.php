<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasApprovalHistories;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'author_id',
    'title',
    'slug',
    'excerpt',
    'content',
    'template',
    'status',
    'published_at',
    'admin_notes',
    'rejection_reason',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_image',
    'canonical_url',
    'og_title',
    'og_description',
    'twitter_title',
    'twitter_description',
    'schema',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasApprovalHistories, HasFactory, HasPublishing, HasSeoFields, HasUniqueSlug;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<ContentBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class);
    }

    /**
     * @return MorphMany<EditorialRevision, $this>
     */
    public function editorialRevisions(): MorphMany
    {
        return $this->morphMany(EditorialRevision::class, 'editorialable');
    }

    /**
     * @return MorphMany<ScheduledPublication, $this>
     */
    public function scheduledPublications(): MorphMany
    {
        return $this->morphMany(ScheduledPublication::class, 'publishable');
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'schema' => 'array',
        ];
    }
}
