<?php

namespace App\Models;

use App\Enums\LearningResourceAccess;
use Database\Factories\CourseResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'course_id',
    'course_lesson_id',
    'media_asset_id',
    'title',
    'description',
    'type',
    'access_level',
    'file_path',
    'external_url',
    'is_downloadable',
    'is_active',
    'sort_order',
])]
class CourseResource extends Model
{
    /** @use HasFactory<CourseResourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    protected function casts(): array
    {
        return [
            'access_level' => LearningResourceAccess::class,
            'is_downloadable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
