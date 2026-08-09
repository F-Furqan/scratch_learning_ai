<?php

namespace App\Enums;

enum InstructorProfileStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
