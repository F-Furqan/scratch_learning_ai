<?php

namespace App\Models;

use Database\Factories\ContentBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'key', 'title', 'body', 'settings', 'sort_order', 'is_active'])]
class ContentBlock extends Model
{
    /** @use HasFactory<ContentBlockFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
