<?php

namespace App\Models;

use Database\Factories\BlockedWordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['word', 'match_type', 'severity', 'is_active', 'notes'])]
class BlockedWord extends Model
{
    /** @use HasFactory<BlockedWordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
