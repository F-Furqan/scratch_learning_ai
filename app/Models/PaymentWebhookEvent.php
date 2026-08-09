<?php

namespace App\Models;

use Database\Factories\PaymentWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'event_id',
    'event_type',
    'status',
    'payload',
    'attempts',
    'last_error',
    'queued_at',
    'processed_at',
])]
class PaymentWebhookEvent extends Model
{
    /** @use HasFactory<PaymentWebhookEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
