<?php

namespace App\Models;

use App\Enums\PaymentOrderStatus;
use Database\Factories\PaymentOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'team_account_id',
    'payment_checkout_id',
    'provider',
    'status',
    'paddle_transaction_id',
    'paddle_customer_id',
    'paddle_subscription_id',
    'currency',
    'subtotal',
    'tax',
    'discount',
    'total',
    'purchased_at',
    'payload',
])]
class PaymentOrder extends Model
{
    /** @use HasFactory<PaymentOrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TeamAccount, $this>
     */
    public function teamAccount(): BelongsTo
    {
        return $this->belongsTo(TeamAccount::class);
    }

    /**
     * @return BelongsTo<PaymentCheckout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
    }

    /**
     * @return HasMany<PaymentOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentOrderStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'purchased_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
