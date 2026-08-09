<?php

namespace App\Models;

use App\Enums\LessonProgressStatus;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'course_id',
    'course_lesson_id',
    'status',
    'progress_seconds',
    'duration_seconds',
    'progress_percent',
    'started_at',
    'last_watched_at',
    'completed_at',
])]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
            'status' => LessonProgressStatus::class,
            'progress_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'progress_percent' => 'integer',
            'started_at' => 'datetime',
            'last_watched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
