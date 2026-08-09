<?php

namespace Database\Factories;

use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrderItem>
 */
class PaymentOrderItemFactory extends Factory
{
    protected $model = PaymentOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_order_id' => PaymentOrder::factory(),
            'payment_product_id' => PaymentProduct::factory(),
            'payment_price_id' => PaymentPrice::factory(),
            'course_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => 1,
            'unit_amount' => 9900,
            'total' => 9900,
            'metadata' => [],
        ];
    }
}
