<?php

namespace App\Enums;

enum GiftPurchaseStatus: string
{
    case Pending = 'pending';
    case Purchased = 'purchased';
    case Delivered = 'delivered';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
