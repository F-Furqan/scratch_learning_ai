<?php

namespace App\Enums;

enum PaymentSubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Canceled = 'canceled';
    case Inactive = 'inactive';
}
