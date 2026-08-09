<?php

namespace App\Enums;

enum EditorialRevisionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Archived = 'archived';
}
