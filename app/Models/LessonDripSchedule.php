<?php

namespace App\Models;

use App\Enums\DripReleaseType;
use Database\Factories\LessonDripScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'course_id',
    'course_lesson_id',
    'release_type',
    'release_after_days',
    'release_at',
    'is_active',
])]
class LessonDripSchedule extends Model
{
    /** @use HasFactory<LessonDripScheduleFactory> */
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

    protected function casts(): array
    {
        return [
            'release_type' => DripReleaseType::class,
            'release_after_days' => 'integer',
            'release_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
