<?php

namespace App\Models;

use App\Enums\EditorialRevisionStatus;
use App\Enums\GrowthStatus;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasApprovalHistories;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'blog_category_id',
    'author_id',
    'featured_image_media_id',
    'title',
    'slug',
    'excerpt',
    'content',
    'status',
    'published_at',
    'is_featured',
    'rejection_reason',
    'admin_notes',
    'copyright_declaration_accepted_at',
    'copyright_declaration_ip',
    'copyright_declaration_user_agent',
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
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasApprovalHistories, HasFactory, HasPublishing, HasSeoFields, HasUniqueSlug, SoftDeletes;

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_image_media_id');
    }

    /**
     * @return BelongsToMany<BlogTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    /**
     * @return HasMany<BlogFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(BlogFaq::class);
    }

    /**
     * @return HasMany<BlogComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    /**
     * @return MorphMany<EditorialRevision, $this>
     */
    public function editorialRevisions(): MorphMany
    {
        return $this->morphMany(EditorialRevision::class, 'editorialable');
    }

    /**
     * @return MorphOne<EditorialRevision, $this>
     */
    public function latestEditorialRevision(): MorphOne
    {
        return $this->morphOne(EditorialRevision::class, 'editorialable')->latestOfMany();
    }

    /**
     * @return MorphOne<EditorialRevision, $this>
     */
    public function activeEditorialRevision(): MorphOne
    {
        return $this->morphOne(EditorialRevision::class, 'editorialable')
            ->whereIn('status', [
                EditorialRevisionStatus::Submitted->value,
                EditorialRevisionStatus::ChangesRequested->value,
            ])
            ->latestOfMany();
    }

    /**
     * @return MorphMany<ScheduledPublication, $this>
     */
    public function scheduledPublications(): MorphMany
    {
        return $this->morphMany(ScheduledPublication::class, 'publishable');
    }

    /**
     * @return MorphMany<SocialShareImage, $this>
     */
    public function socialShareImages(): MorphMany
    {
        return $this->morphMany(SocialShareImage::class, 'shareable');
    }

    /**
     * @return MorphMany<CreatorContentDeletionRequest, $this>
     */
    public function deletionRequests(): MorphMany
    {
        return $this->morphMany(CreatorContentDeletionRequest::class, 'content');
    }

    /**
     * @return MorphOne<SocialShareImage, $this>
     */
    public function activeSocialShareImage(): MorphOne
    {
        return $this->morphOne(SocialShareImage::class, 'shareable')
            ->where('status', GrowthStatus::Active->value)
            ->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'copyright_declaration_accepted_at' => 'datetime',
            'deleted_at' => 'datetime',
            'is_featured' => 'boolean',
            'schema' => 'array',
        ];
    }
}
