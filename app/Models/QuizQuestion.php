<?php

namespace App\Models;

use App\Enums\QuizQuestionType;
use Database\Factories\QuizQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quiz_id',
    'question',
    'type',
    'points',
    'options',
    'correct_answer',
    'explanation',
    'sort_order',
])]
class QuizQuestion extends Model
{
    /** @use HasFactory<QuizQuestionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    protected function casts(): array
    {
        return [
            'type' => QuizQuestionType::class,
            'points' => 'integer',
            'options' => 'array',
            'correct_answer' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
