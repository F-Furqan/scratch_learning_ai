<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\GrowthStatus;
use Database\Factories\PaymentDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_product_id',
    'payment_price_id',
    'course_id',
    'name',
    'code',
    'description',
    'type',
    'value',
    'currency',
    'status',
    'is_launch_offer',
    'paddle_discount_id',
    'max_redemptions',
    'redemptions_count',
    'per_user_limit',
    'starts_at',
    'ends_at',
    'metadata',
])]
class PaymentDiscount extends Model
{
    /** @use HasFactory<PaymentDiscountFactory> */
    use HasFactory;

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
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<PaymentDiscountRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PaymentDiscountRedemption::class);
    }

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'status' => GrowthStatus::class,
            'is_launch_offer' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
