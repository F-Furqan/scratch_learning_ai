<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'status', 'started_at', 'completed_at', 'duration_ms', 'metadata'])]
class SchedulerHeartbeat extends Model
{
    protected $table = 'operations_scheduler_heartbeats';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'metadata' => 'array',
        ];
    }
}
