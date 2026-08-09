<?php

namespace App\Enums;

enum LeadSubmissionStatus: string
{
    case New = 'new';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
