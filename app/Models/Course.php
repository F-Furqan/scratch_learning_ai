<?php

namespace App\Models;

use App\Enums\EditorialRevisionStatus;
use App\Enums\GrowthStatus;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasApprovalHistories;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'course_category_id',
    'course_subcategory_id',
    'created_by',
    'thumbnail_media_id',
    'ownership_video_media_id',
    'ownership_video_url',
    'ownership_statement',
    'ownership_confirmed_at',
    'title',
    'slug',
    'short_description',
    'description',
    'intro_video_url',
    'level',
    'language',
    'price',
    'is_free',
    'status',
    'published_at',
    'admin_notes',
    'rejection_reason',
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
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasApprovalHistories, HasFactory, HasPublishing, HasSeoFields, HasUniqueSlug, SoftDeletes;

    /**
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /**
     * @return BelongsTo<CourseSubcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(CourseSubcategory::class, 'course_subcategory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function ownershipVideo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'ownership_video_media_id');
    }

    /**
     * @return HasMany<CourseSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class);
    }

    /**
     * @return HasMany<CourseLesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /**
     * @return HasMany<CourseFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(CourseFaq::class);
    }

    /**
     * @return HasOne<PaymentProduct, $this>
     */
    public function paymentProduct(): HasOne
    {
        return $this->hasOne(PaymentProduct::class);
    }

    /**
     * @return HasMany<CourseEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<CourseResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class);
    }

    /**
     * @return HasMany<DiscussionForum, $this>
     */
    public function discussionForums(): HasMany
    {
        return $this->hasMany(DiscussionForum::class);
    }

    /**
     * @return HasMany<CommunityGroup, $this>
     */
    public function communityGroups(): HasMany
    {
        return $this->hasMany(CommunityGroup::class);
    }

    /**
     * @return HasMany<Quiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
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

    /**
     * @return HasMany<RevenueShareRule, $this>
     */
    public function revenueShareRules(): HasMany
    {
        return $this->hasMany(RevenueShareRule::class);
    }

    /**
     * @return BelongsToMany<LearningPath, $this>
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_courses')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<SkillTrack, $this>
     */
    public function skillTracks(): BelongsToMany
    {
        return $this->belongsToMany(SkillTrack::class, 'skill_track_courses')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<CourseBundle, $this>
     */
    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(CourseBundle::class, 'course_bundle_courses')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_free' => 'boolean',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'copyright_declaration_accepted_at' => 'datetime',
            'deleted_at' => 'datetime',
            'ownership_confirmed_at' => 'datetime',
            'schema' => 'array',
        ];
    }
}
