<?php

namespace App\Models;

use App\Enums\ScheduledPublicationStatus;
use Database\Factories\ScheduledPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'publishable_type',
    'publishable_id',
    'editorial_revision_id',
    'created_by',
    'approved_by',
    'publish_at',
    'timezone',
    'status',
    'published_at',
    'failure_reason',
    'metadata',
])]
class ScheduledPublication extends Model
{
    /** @use HasFactory<ScheduledPublicationFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'status' => ScheduledPublicationStatus::class,
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
