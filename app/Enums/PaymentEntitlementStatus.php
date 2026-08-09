<?php

namespace App\Enums;

enum PaymentEntitlementStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
