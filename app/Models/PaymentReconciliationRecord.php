<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'event_id',
    'record_type',
    'status',
    'paddle_transaction_id',
    'paddle_subscription_id',
    'paddle_customer_id',
    'payload',
    'reconciled_at',
    'notes',
])]
class PaymentReconciliationRecord extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }
}
