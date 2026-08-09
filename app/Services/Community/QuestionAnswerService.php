<?php

namespace App\Services\Community;

use App\Enums\CommunityContentStatus;
use App\Enums\ReputationEventType;
use App\Models\CourseQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuestionAnswerService
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly ReputationService $reputation,
    ) {}

    public function answer(CourseQuestion $question, User $user, string $body): LessonQuestionAnswer
    {
        return DB::transaction(function () use ($question, $user, $body): LessonQuestionAnswer {
            $answer = LessonQuestionAnswer::query()->create([
                'course_question_id' => $question->id,
                'user_id' => $user->id,
                'body' => $body,
                'status' => CommunityContentStatus::Pending,
            ]);

            $this->moderation->moderate($answer, $body, $user, 'lesson_question_answer');
            $this->reputation->record($user, $user, ReputationEventType::AnswerPosted, 1, $answer, 'Posted a lesson answer.');

            return $answer;
        });
    }

    public function accept(CourseQuestion $question, LessonQuestionAnswer $answer, User $acceptedBy): LessonQuestionAnswer
    {
        return DB::transaction(function () use ($question, $answer, $acceptedBy): LessonQuestionAnswer {
            abort_unless((int) $answer->course_question_id === (int) $question->id, 404);

            $answer->forceFill([
                'status' => CommunityContentStatus::Approved,
                'accepted_by' => $acceptedBy->id,
                'accepted_at' => now(),
            ])->save();

            $question->forceFill([
                'accepted_answer_id' => $answer->id,
                'answered_by' => $answer->user_id,
                'answered_at' => now(),
            ])->save();

            if ($answer->user) {
                $this->reputation->record($answer->user, $acceptedBy, ReputationEventType::AnswerAccepted, 15, $answer, 'Lesson answer accepted.');
            }

            return $answer;
        });
    }
}
