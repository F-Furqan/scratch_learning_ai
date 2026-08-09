<?php

namespace App\Enums;

enum ScheduledPublicationStatus: string
{
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Canceled = 'canceled';
    case Failed = 'failed';
}
