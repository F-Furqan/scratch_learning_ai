<?php

namespace App\Enums;

enum AdZoneStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
