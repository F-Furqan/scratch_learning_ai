<?php

namespace App\Models;

use App\Enums\PaymentSubscriptionStatus;
use Carbon\CarbonInterface;
use Database\Factories\PaymentSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'team_account_id',
    'payment_product_id',
    'payment_price_id',
    'provider',
    'status',
    'paddle_subscription_id',
    'paddle_customer_id',
    'currency',
    'quantity',
    'current_period_starts_at',
    'current_period_ends_at',
    'trial_ends_at',
    'canceled_at',
    'next_billed_at',
    'payload',
])]
class PaymentSubscription extends Model
{
    /** @use HasFactory<PaymentSubscriptionFactory> */
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
     * @return BelongsTo<PaymentProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PaymentProduct::class, 'payment_product_id');
    }

    /**
     * @return BelongsTo<PaymentPrice, $this>
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PaymentPrice::class, 'payment_price_id');
    }

    /**
     * @return HasMany<PaymentEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(PaymentEntitlement::class);
    }

    public function isActiveForAccess(): bool
    {
        $status = $this->getAttribute('status');
        $periodEndsAt = $this->getAttribute('current_period_ends_at');

        return in_array($status, [PaymentSubscriptionStatus::Active, PaymentSubscriptionStatus::Trialing], true)
            || ($status === PaymentSubscriptionStatus::Canceled
                && $periodEndsAt instanceof CarbonInterface
                && $periodEndsAt->isFuture());
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentSubscriptionStatus::class,
            'quantity' => 'integer',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'next_billed_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
