<?php

namespace App\Enums;

enum QuizAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Passed = 'passed';
    case Failed = 'failed';
}
