<?php

namespace App\Models;

use App\Enums\CommunityContentStatus;
use Database\Factories\LessonQuestionAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['course_question_id', 'user_id', 'body', 'status', 'upvotes_count', 'accepted_by', 'accepted_at'])]
class LessonQuestionAnswer extends Model
{
    /** @use HasFactory<LessonQuestionAnswerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CourseQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CourseQuestion::class, 'course_question_id');
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
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return MorphMany<CommunityReaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(CommunityReaction::class, 'reactable');
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
            'status' => CommunityContentStatus::class,
            'accepted_at' => 'datetime',
        ];
    }
}
