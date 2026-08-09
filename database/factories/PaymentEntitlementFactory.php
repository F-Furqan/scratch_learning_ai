<?php

namespace Database\Factories;

use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use App\Models\PaymentEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentEntitlement>
 */
class PaymentEntitlementFactory extends Factory
{
    protected $model = PaymentEntitlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_account_id' => null,
            'entitlementable_type' => null,
            'entitlementable_id' => null,
            'payment_order_id' => null,
            'payment_subscription_id' => null,
            'type' => PaymentEntitlementType::PremiumLibrary,
            'status' => PaymentEntitlementStatus::Active,
            'source' => 'paddle',
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'metadata' => [],
        ];
    }
}
