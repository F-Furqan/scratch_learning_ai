<?php

namespace App\Enums;

enum CheckoutRecoveryStatus: string
{
    case Open = 'open';
    case Reminded = 'reminded';
    case Recovered = 'recovered';
    case Expired = 'expired';
    case Dismissed = 'dismissed';
}
