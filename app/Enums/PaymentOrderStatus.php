<?php

namespace App\Enums;

enum PaymentOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case Canceled = 'canceled';
    case PastDue = 'past_due';
}
