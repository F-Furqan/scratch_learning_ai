<?php

namespace App\Models;

use App\Enums\PaymentCheckoutStatus;
use Database\Factories\PaymentCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'payment_price_id',
    'course_id',
    'team_account_id',
    'payment_discount_id',
    'quantity',
    'discount_amount',
    'status',
    'paddle_transaction_id',
    'checkout_url',
    'recovery_email',
    'custom_data',
    'expires_at',
])]
class PaymentCheckout extends Model
{
    /** @use HasFactory<PaymentCheckoutFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentPrice, $this>
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PaymentPrice::class, 'payment_price_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<TeamAccount, $this>
     */
    public function teamAccount(): BelongsTo
    {
        return $this->belongsTo(TeamAccount::class);
    }

    /**
     * @return BelongsTo<PaymentDiscount, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(PaymentDiscount::class, 'payment_discount_id');
    }

    /**
     * @return HasOne<PaymentOrder, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(PaymentOrder::class);
    }

    /**
     * @return HasOne<GiftPurchase, $this>
     */
    public function giftPurchase(): HasOne
    {
        return $this->hasOne(GiftPurchase::class);
    }

    /**
     * @return HasOne<AbandonedCheckoutRecovery, $this>
     */
    public function recovery(): HasOne
    {
        return $this->hasOne(AbandonedCheckoutRecovery::class, 'payment_checkout_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'discount_amount' => 'integer',
            'status' => PaymentCheckoutStatus::class,
            'custom_data' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
