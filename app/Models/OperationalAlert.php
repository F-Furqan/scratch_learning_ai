<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'source',
    'severity',
    'status',
    'title',
    'message',
    'occurrence_count',
    'first_detected_at',
    'last_detected_at',
    'acknowledged_by',
    'acknowledged_at',
    'resolved_by',
    'resolved_at',
    'resolution_note',
    'metadata',
])]
class OperationalAlert extends Model
{
    protected $table = 'operations_alerts';

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return [
            'occurrence_count' => 'integer',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
