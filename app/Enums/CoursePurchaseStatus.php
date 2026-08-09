<?php

namespace App\Enums;

enum CoursePurchaseStatus: string
{
    case Active = 'active';
    case Refunded = 'refunded';
    case Revoked = 'revoked';
}
