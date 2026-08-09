<?php

namespace App\Enums;

enum PaymentEntitlementType: string
{
    case Course = 'course';
    case PremiumLibrary = 'premium_library';
    case AdFree = 'ad_free';
    case TeamSeat = 'team_seat';
    case Bundle = 'bundle';
}
