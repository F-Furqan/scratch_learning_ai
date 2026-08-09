<?php

namespace App\Models;

use Database\Factories\PaymentDiscountRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_discount_id', 'user_id', 'payment_checkout_id', 'payment_order_id', 'code', 'amount', 'redeemed_at', 'metadata'])]
class PaymentDiscountRedemption extends Model
{
    /** @use HasFactory<PaymentDiscountRedemptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PaymentDiscount, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(PaymentDiscount::class, 'payment_discount_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentCheckout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
    }

    /**
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'redeemed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
