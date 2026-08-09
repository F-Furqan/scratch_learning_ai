<?php

namespace App\Models;

use App\Enums\LearningCatalogStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\LearningPathFactory;
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
class LearningPath extends Model
{
    /** @use HasFactory<LearningPathFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_courses')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_path_enrollments')
            ->withPivot(['status', 'started_at', 'completed_at'])
            ->withTimestamps();
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
