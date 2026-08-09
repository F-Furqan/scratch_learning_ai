<?php

namespace App\Enums;

enum LearningResourceAccess: string
{
    case Free = 'free';
    case Enrolled = 'enrolled';
    case Purchased = 'purchased';
}
