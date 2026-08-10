<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['level', 'channel', 'environment', 'message', 'context', 'occurred_at'])]
class ApplicationLogEntry extends Model
{
    protected $table = 'operations_log_entries';

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
