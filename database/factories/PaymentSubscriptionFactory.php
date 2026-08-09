<?php

namespace Database\Factories;

use App\Enums\PaymentSubscriptionStatus;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSubscription>
 */
class PaymentSubscriptionFactory extends Factory
{
    protected $model = PaymentSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_account_id' => null,
            'payment_product_id' => PaymentProduct::factory(),
            'payment_price_id' => PaymentPrice::factory(),
            'provider' => 'paddle',
            'status' => PaymentSubscriptionStatus::Active,
            'paddle_subscription_id' => 'sub_'.strtolower(fake()->bothify('??????????????????????????')),
            'paddle_customer_id' => 'ctm_'.strtolower(fake()->bothify('??????????????????????????')),
            'currency' => 'USD',
            'quantity' => 1,
            'current_period_starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'canceled_at' => null,
            'next_billed_at' => now()->addMonth(),
            'payload' => [],
        ];
    }
}
