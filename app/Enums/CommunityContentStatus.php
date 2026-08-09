<?php

namespace App\Enums;

enum CommunityContentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';
    case Hidden = 'hidden';
}
