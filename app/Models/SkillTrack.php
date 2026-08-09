<?php

namespace App\Models;

use App\Enums\LearningCatalogStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\SkillTrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'status',
    'sort_order',
    'metadata',
])]
class SkillTrack extends Model
{
    /** @use HasFactory<SkillTrackFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'skill_track_courses')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    protected function casts(): array
    {
        return [
            'status' => LearningCatalogStatus::class,
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
