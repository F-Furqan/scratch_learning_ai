<?php

namespace Database\Factories;

use App\Enums\PaymentOrderStatus;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrder>
 */
class PaymentOrderFactory extends Factory
{
    protected $model = PaymentOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_account_id' => null,
            'payment_checkout_id' => null,
            'provider' => 'paddle',
            'status' => PaymentOrderStatus::Completed,
            'paddle_transaction_id' => 'txn_'.strtolower(fake()->bothify('??????????????????????????')),
            'paddle_customer_id' => 'ctm_'.strtolower(fake()->bothify('??????????????????????????')),
            'paddle_subscription_id' => null,
            'currency' => 'USD',
            'subtotal' => 9900,
            'tax' => 0,
            'discount' => 0,
            'total' => 9900,
            'purchased_at' => now(),
            'payload' => [],
        ];
    }
}
