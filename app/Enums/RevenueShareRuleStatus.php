<?php

namespace App\Enums;

enum RevenueShareRuleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
