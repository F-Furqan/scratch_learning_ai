<?php

namespace App\Services\Growth;

use App\Enums\CheckoutRecoveryStatus;
use App\Models\AbandonedCheckoutRecovery;
use App\Models\PaymentCheckout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutRecoveryService
{
    public function createForCheckout(PaymentCheckout $checkout, User $user, ?string $email = null, ?Request $request = null): AbandonedCheckoutRecovery
    {
        $token = Str::random(40);
        $email = $email ?: $user->email;

        $recovery = AbandonedCheckoutRecovery::query()->updateOrCreate(
            ['payment_checkout_id' => $checkout->id],
            [
                'user_id' => $user->id,
                'email' => $email,
                'status' => CheckoutRecoveryStatus::Open,
                'recovery_token' => $token,
                'recovery_url' => route('checkout.recoveries.show', $token),
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'source_url' => $request?->fullUrl(),
                ],
            ],
        );

        $checkout->forceFill(['recovery_email' => $email])->save();

        return $recovery;
    }

    public function markReminderOpened(AbandonedCheckoutRecovery $recovery): void
    {
        $status = $this->enumValue($recovery->getAttribute('status'));

        if ($status === CheckoutRecoveryStatus::Recovered->value) {
            return;
        }

        $recovery->forceFill([
            'status' => CheckoutRecoveryStatus::Reminded,
            'reminder_count' => $recovery->reminder_count + 1,
            'last_reminded_at' => now(),
        ])->save();
    }

    public function markRecovered(PaymentCheckout $checkout): void
    {
        $recovery = $checkout->recovery;

        if (! $recovery) {
            return;
        }

        $recovery->forceFill([
            'status' => CheckoutRecoveryStatus::Recovered,
            'recovered_at' => now(),
        ])->save();
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
