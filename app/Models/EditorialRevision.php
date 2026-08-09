<?php

namespace App\Models;

use App\Enums\EditorialRevisionStatus;
use App\Models\Concerns\HasApprovalHistories;
use Database\Factories\EditorialRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'editorialable_type',
    'editorialable_id',
    'author_id',
    'reviewer_id',
    'title',
    'summary',
    'payload',
    'status',
    'submitted_at',
    'reviewed_at',
    'scheduled_at',
    'published_at',
])]
class EditorialRevision extends Model
{
    /** @use HasFactory<EditorialRevisionFactory> */
    use HasApprovalHistories, HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function editorialable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return HasMany<ReviewerComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewerComment::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => EditorialRevisionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
