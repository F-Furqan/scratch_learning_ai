<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\VideoType;
use App\Models\Concerns\HasApprovalHistories;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CourseLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'course_id',
    'course_section_id',
    'title',
    'slug',
    'order_number',
    'content',
    'video_type',
    'video_url',
    'video_file_id',
    'is_free',
    'is_paid',
    'preview_word_limit',
    'allow_comments',
    'allow_questions',
    'status',
    'published_at',
    'admin_notes',
    'rejection_reason',
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
class CourseLesson extends Model
{
    /** @use HasFactory<CourseLessonFactory> */
    use HasApprovalHistories, HasFactory, HasPublishing, HasSeoFields, HasUniqueSlug;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function videoFile(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'video_file_id');
    }

    /**
     * @return HasMany<CourseFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(CourseFaq::class);
    }

    /**
     * @return HasMany<CourseQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuestion::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<LessonNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LessonNote::class);
    }

    /**
     * @return HasMany<LessonBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(LessonBookmark::class);
    }

    /**
     * @return HasMany<CourseResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class);
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
     * @return HasOne<LessonDripSchedule, $this>
     */
    public function dripSchedule(): HasOne
    {
        return $this->hasOne(LessonDripSchedule::class);
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

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return ['course_id'];
    }

    protected function casts(): array
    {
        return [
            'video_type' => VideoType::class,
            'is_free' => 'boolean',
            'is_paid' => 'boolean',
            'allow_comments' => 'boolean',
            'allow_questions' => 'boolean',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'schema' => 'array',
        ];
    }
}
