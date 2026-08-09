<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CourseSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'title', 'slug', 'description', 'sort_order', 'status', 'published_at'])]
class CourseSection extends Model
{
    /** @use HasFactory<CourseSectionFactory> */
    use HasFactory, HasPublishing, HasUniqueSlug;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<CourseLesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return ['course_id'];
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
