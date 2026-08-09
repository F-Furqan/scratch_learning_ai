<?php

namespace Database\Factories;

use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentDiscountRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentDiscountRedemption>
 */
class PaymentDiscountRedemptionFactory extends Factory
{
    protected $model = PaymentDiscountRedemption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_discount_id' => PaymentDiscount::factory(),
            'user_id' => User::factory(),
            'payment_checkout_id' => PaymentCheckout::factory(),
            'payment_order_id' => null,
            'code' => strtoupper(fake()->bothify('SAVE##??')),
            'amount' => fake()->numberBetween(500, 5000),
            'redeemed_at' => null,
            'metadata' => [],
        ];
    }
}
