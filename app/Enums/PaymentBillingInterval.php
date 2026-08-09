<?php

namespace App\Enums;

enum PaymentBillingInterval: string
{
    case OneTime = 'one_time';
    case Month = 'month';
    case Year = 'year';
}
