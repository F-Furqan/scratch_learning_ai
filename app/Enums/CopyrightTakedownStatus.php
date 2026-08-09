<?php

namespace App\Enums;

enum CopyrightTakedownStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
