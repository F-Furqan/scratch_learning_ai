<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    public $timestamps = false;

    protected $table = 'jobs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'reserved_at' => 'integer',
            'available_at' => 'integer',
            'created_at' => 'integer',
        ];
    }
}
