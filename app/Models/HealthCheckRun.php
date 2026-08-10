<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['check_name', 'status', 'metadata', 'checked_at'])]
class HealthCheckRun extends Model
{
    protected $table = 'operations_health_check_runs';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
