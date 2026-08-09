<?php

namespace App\Models;

use App\Enums\CourseEnrollmentStatus;
use Database\Factories\CourseEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'course_id',
    'last_lesson_id',
    'source',
    'status',
    'progress_percent',
    'started_at',
    'completed_at',
])]
class CourseEnrollment extends Model
{
    /** @use HasFactory<CourseEnrollmentFactory> */
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
    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'last_lesson_id');
    }

    protected function casts(): array
    {
        return [
            'status' => CourseEnrollmentStatus::class,
            'progress_percent' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
