<?php

namespace App\Models;

use App\Enums\CreatorContentDeletionStatus;
use App\Models\Concerns\HasApprovalHistories;
use Database\Factories\CreatorContentDeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'requester_id',
    'content_type',
    'content_id',
    'status',
    'reason',
    'decided_by',
    'decided_at',
    'admin_note',
])]
class CreatorContentDeletionRequest extends Model
{
    /** @use HasFactory<CreatorContentDeletionRequestFactory> */
    use HasApprovalHistories, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'status' => CreatorContentDeletionStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
