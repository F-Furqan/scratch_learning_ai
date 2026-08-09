<?php

namespace App\Enums;

enum AdPricingModel: string
{
    case Cpm = 'cpm';
    case Cpc = 'cpc';
    case Flat = 'flat';
}
