<?php

namespace App\Models;

use Database\Factories\PaymentOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_order_id',
    'payment_product_id',
    'payment_price_id',
    'course_id',
    'description',
    'quantity',
    'unit_amount',
    'total',
    'metadata',
])]
class PaymentOrderItem extends Model
{
    /** @use HasFactory<PaymentOrderItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
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
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'total' => 'integer',
            'metadata' => 'array',
        ];
    }
}
