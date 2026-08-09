<?php

namespace App\Enums;

enum PaymentCheckoutStatus: string
{
    case Initiated = 'initiated';
    case Ready = 'ready';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Failed = 'failed';
}
