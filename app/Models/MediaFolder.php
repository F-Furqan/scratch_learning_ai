<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\MediaFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'path'])]
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'folder_id');
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return ['parent_id'];
    }
}
