<?php

namespace App\Enums;

enum BackupVerificationStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
