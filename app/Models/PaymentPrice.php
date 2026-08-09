<?php

namespace App\Models;

use App\Enums\PaymentBillingInterval;
use Database\Factories\PaymentPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_product_id',
    'name',
    'paddle_price_id',
    'billing_interval',
    'is_recurring',
    'currency',
    'amount',
    'trial_days',
    'seat_min',
    'seat_max',
    'is_active',
    'metadata',
])]
class PaymentPrice extends Model
{
    /** @use HasFactory<PaymentPriceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PaymentProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PaymentProduct::class, 'payment_product_id');
    }

    /**
     * @return HasMany<PaymentCheckout, $this>
     */
    public function checkouts(): HasMany
    {
        return $this->hasMany(PaymentCheckout::class);
    }

    public function formattedAmount(): string
    {
        return strtoupper($this->currency).' '.number_format($this->amount / 100, 2);
    }

    protected function casts(): array
    {
        return [
            'billing_interval' => PaymentBillingInterval::class,
            'is_recurring' => 'boolean',
            'amount' => 'integer',
            'trial_days' => 'integer',
            'seat_min' => 'integer',
            'seat_max' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
