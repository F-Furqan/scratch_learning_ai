<?php

namespace App\Enums;

enum QuizQuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultiSelect = 'multi_select';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
}
