<?php

namespace App\Models;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\DiscussionForumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id',
    'course_category_id',
    'created_by',
    'title',
    'slug',
    'description',
    'visibility',
    'status',
    'threads_count',
    'posts_count',
    'sort_order',
    'metadata',
])]
class DiscussionForum extends Model
{
    /** @use HasFactory<DiscussionForumFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DiscussionThread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(DiscussionThread::class);
    }

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return ['course_id', 'course_category_id'];
    }

    protected function casts(): array
    {
        return [
            'visibility' => CommunityVisibility::class,
            'status' => CommunityContentStatus::class,
            'metadata' => 'array',
        ];
    }
}
