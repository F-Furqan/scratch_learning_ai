<?php

namespace App\Enums;

enum TeamAccountStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Inactive = 'inactive';
}
