<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'is_active',
    'seo_title',
    'seo_description',
    'seo_image',
    'canonical_url',
    'og_title',
    'og_description',
    'twitter_title',
    'twitter_description',
    'schema',
])]
class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory, HasSeoFields, HasUniqueSlug;

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'schema' => 'array',
        ];
    }
}
