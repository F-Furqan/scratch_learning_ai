<?php

namespace App\Enums;

enum AdvertiserRequestStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
