<?php

namespace App\Models;

use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use Carbon\CarbonInterface;
use Database\Factories\PaymentEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'team_account_id',
    'entitlementable_type',
    'entitlementable_id',
    'payment_order_id',
    'payment_subscription_id',
    'type',
    'status',
    'source',
    'starts_at',
    'ends_at',
    'metadata',
])]
class PaymentEntitlement extends Model
{
    /** @use HasFactory<PaymentEntitlementFactory> */
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
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    /**
     * @return BelongsTo<PaymentSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PaymentSubscription::class, 'payment_subscription_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entitlementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isActive(): bool
    {
        $startsAt = $this->getAttribute('starts_at');
        $endsAt = $this->getAttribute('ends_at');

        return $this->getAttribute('status') === PaymentEntitlementStatus::Active
            && ($startsAt === null || ($startsAt instanceof CarbonInterface && $startsAt->isPast()))
            && ($endsAt === null || ($endsAt instanceof CarbonInterface && $endsAt->isFuture()));
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentEntitlementType::class,
            'status' => PaymentEntitlementStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
