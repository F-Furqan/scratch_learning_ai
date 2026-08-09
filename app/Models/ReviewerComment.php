<?php

namespace App\Models;

use Database\Factories\ReviewerCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_revision_id',
    'reviewer_id',
    'resolved_by',
    'field_path',
    'body',
    'is_resolved',
    'resolved_at',
])]
class ReviewerComment extends Model
{
    /** @use HasFactory<ReviewerCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<EditorialRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(EditorialRevision::class, 'editorial_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
