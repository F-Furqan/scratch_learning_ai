<?php

namespace App\Enums;

enum RevenueShareRuleType: string
{
    case Course = 'course';
    case Product = 'product';
    case Instructor = 'instructor';
    case PlatformDefault = 'platform_default';
}
