<?php

namespace App\Enums;

enum PaymentProductType: string
{
    case Course = 'course';
    case PremiumLibrary = 'premium_library';
    case Subscription = 'subscription';
    case Team = 'team';
    case AdFree = 'ad_free';
    case Bundle = 'bundle';
}
