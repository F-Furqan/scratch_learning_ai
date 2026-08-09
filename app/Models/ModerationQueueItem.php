<?php

namespace App\Models;

use App\Enums\CommunityContentStatus;
use Database\Factories\ModerationQueueItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'subject_type',
    'subject_id',
    'reporter_id',
    'assigned_to',
    'status',
    'reason',
    'spam_score',
    'matched_terms',
    'reviewed_at',
    'resolution_note',
])]
class ModerationQueueItem extends Model
{
    /** @use HasFactory<ModerationQueueItemFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'status' => CommunityContentStatus::class,
            'matched_terms' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
