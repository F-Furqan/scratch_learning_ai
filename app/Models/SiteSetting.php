<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'is_encrypted'])]
class SiteSetting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_encrypted' => 'boolean',
        ];
    }
}
