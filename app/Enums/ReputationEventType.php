<?php

namespace App\Enums;

enum ReputationEventType: string
{
    case QuestionAsked = 'question_asked';
    case AnswerPosted = 'answer_posted';
    case AnswerAccepted = 'answer_accepted';
    case UpvoteReceived = 'upvote_received';
    case ReportAccepted = 'report_accepted';
    case SpamPenalty = 'spam_penalty';
    case AdminAdjustment = 'admin_adjustment';
}
