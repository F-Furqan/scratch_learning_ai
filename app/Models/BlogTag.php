<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\BlogTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class BlogTag extends Model
{
    /** @use HasFactory<BlogTagFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsToMany<BlogPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }
}
