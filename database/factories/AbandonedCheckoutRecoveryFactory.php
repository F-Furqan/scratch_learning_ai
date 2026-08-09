<?php

namespace Database\Factories;

use App\Enums\CheckoutRecoveryStatus;
use App\Models\AbandonedCheckoutRecovery;
use App\Models\PaymentCheckout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AbandonedCheckoutRecovery>
 */
class AbandonedCheckoutRecoveryFactory extends Factory
{
    protected $model = AbandonedCheckoutRecovery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(40);

        return [
            'payment_checkout_id' => PaymentCheckout::factory(),
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'status' => CheckoutRecoveryStatus::Open,
            'recovery_token' => $token,
            'recovery_url' => url('/checkout/recover/'.$token),
            'reminder_count' => 0,
            'last_reminded_at' => null,
            'recovered_at' => null,
            'expires_at' => now()->addDays(7),
            'metadata' => [],
        ];
    }
}
