<?php

namespace App\Models;

use App\Enums\CommunityContentStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\DiscussionThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'discussion_forum_id',
    'user_id',
    'title',
    'slug',
    'body',
    'status',
    'is_pinned',
    'is_locked',
    'replies_count',
    'upvotes_count',
    'last_activity_at',
])]
class DiscussionThread extends Model
{
    /** @use HasFactory<DiscussionThreadFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<DiscussionForum, $this>
     */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(DiscussionForum::class, 'discussion_forum_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DiscussionPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class);
    }

    /**
     * @return MorphMany<CommunityReaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(CommunityReaction::class, 'reactable');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return ['discussion_forum_id'];
    }

    protected function casts(): array
    {
        return [
            'status' => CommunityContentStatus::class,
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }
}
