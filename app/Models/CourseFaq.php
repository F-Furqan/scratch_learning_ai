<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublishing;
use Database\Factories\CourseFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'course_lesson_id', 'question', 'answer', 'sort_order', 'status', 'published_at'])]
class CourseFaq extends Model
{
    /** @use HasFactory<CourseFaqFactory> */
    use HasFactory, HasPublishing;

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
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
