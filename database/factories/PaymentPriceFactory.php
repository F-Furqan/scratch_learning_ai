<?php

namespace Database\Factories;

use App\Enums\PaymentBillingInterval;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentPrice>
 */
class PaymentPriceFactory extends Factory
{
    protected $model = PaymentPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_product_id' => PaymentProduct::factory(),
            'name' => 'Standard',
            'paddle_price_id' => 'pri_'.strtolower(fake()->bothify('??????????????????????????')),
            'billing_interval' => PaymentBillingInterval::OneTime,
            'is_recurring' => false,
            'currency' => 'USD',
            'amount' => 9900,
            'trial_days' => null,
            'seat_min' => null,
            'seat_max' => null,
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Monthly',
            'billing_interval' => PaymentBillingInterval::Month,
            'is_recurring' => true,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Yearly',
            'billing_interval' => PaymentBillingInterval::Year,
            'is_recurring' => true,
        ]);
    }
}
