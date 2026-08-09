<?php

namespace Database\Factories;

use App\Enums\GiftPurchaseStatus;
use App\Models\GiftPurchase;
use App\Models\PaymentCheckout;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GiftPurchase>
 */
class GiftPurchaseFactory extends Factory
{
    protected $model = GiftPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchaser_id' => User::factory(),
            'recipient_user_id' => null,
            'course_id' => null,
            'course_bundle_id' => null,
            'payment_product_id' => PaymentProduct::factory(),
            'payment_price_id' => PaymentPrice::factory(),
            'payment_checkout_id' => PaymentCheckout::factory(),
            'payment_order_id' => null,
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'code' => Str::upper(Str::random(12)),
            'status' => GiftPurchaseStatus::Pending,
            'message' => fake()->sentence(),
            'purchased_at' => null,
            'delivered_at' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addYear(),
            'metadata' => [],
        ];
    }
}
