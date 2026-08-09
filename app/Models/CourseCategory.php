<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CourseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'sort_order',
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
class CourseCategory extends Model
{
    /** @use HasFactory<CourseCategoryFactory> */
    use HasFactory, HasSeoFields, HasUniqueSlug;

    /**
     * @return HasMany<CourseSubcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(CourseSubcategory::class);
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @return HasMany<DiscussionForum, $this>
     */
    public function discussionForums(): HasMany
    {
        return $this->hasMany(DiscussionForum::class);
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
