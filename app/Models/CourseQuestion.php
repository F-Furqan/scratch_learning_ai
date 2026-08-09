<?php

namespace App\Models;

use App\Enums\PublishStatus;
use Database\Factories\CourseQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['course_id', 'course_lesson_id', 'user_id', 'title', 'body', 'answered_by', 'answer', 'accepted_answer_id', 'answered_at', 'status'])]
class CourseQuestion extends Model
{
    /** @use HasFactory<CourseQuestionFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @return HasMany<LessonQuestionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(LessonQuestionAnswer::class);
    }

    /**
     * @return BelongsTo<LessonQuestionAnswer, $this>
     */
    public function acceptedAnswer(): BelongsTo
    {
        return $this->belongsTo(LessonQuestionAnswer::class, 'accepted_answer_id');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'answered_at' => 'datetime',
        ];
    }
}
