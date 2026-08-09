<?php

namespace Database\Factories;

use App\Enums\PaymentCheckoutStatus;
use App\Models\PaymentCheckout;
use App\Models\PaymentPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentCheckout>
 */
class PaymentCheckoutFactory extends Factory
{
    protected $model = PaymentCheckout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'payment_price_id' => PaymentPrice::factory(),
            'course_id' => null,
            'team_account_id' => null,
            'quantity' => 1,
            'status' => PaymentCheckoutStatus::Ready,
            'paddle_transaction_id' => 'txn_'.strtolower(fake()->bothify('??????????????????????????')),
            'checkout_url' => 'https://sandbox-checkout.paddle.com/checkout/'.fake()->uuid(),
            'custom_data' => [],
            'expires_at' => now()->addHour(),
        ];
    }
}
