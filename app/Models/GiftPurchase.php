<?php

namespace App\Models;

use App\Enums\GiftPurchaseStatus;
use Database\Factories\GiftPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchaser_id',
    'recipient_user_id',
    'course_id',
    'course_bundle_id',
    'payment_product_id',
    'payment_price_id',
    'payment_checkout_id',
    'payment_order_id',
    'recipient_email',
    'recipient_name',
    'code',
    'status',
    'message',
    'purchased_at',
    'delivered_at',
    'redeemed_at',
    'expires_at',
    'metadata',
])]
class GiftPurchase extends Model
{
    /** @use HasFactory<GiftPurchaseFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseBundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(CourseBundle::class, 'course_bundle_id');
    }

    /**
     * @return BelongsTo<PaymentCheckout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
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
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GiftPurchaseStatus::class,
            'purchased_at' => 'datetime',
            'delivered_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
