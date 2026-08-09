<?php

namespace App\Enums;

enum PaymentProductStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
