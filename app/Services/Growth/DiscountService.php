<?php

namespace App\Services\Growth;

use App\Enums\DiscountType;
use App\Enums\GrowthStatus;
use App\Models\Course;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentDiscountRedemption;
use App\Models\PaymentPrice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function resolve(?string $code, User $user, PaymentPrice $price, ?Course $course = null): ?PaymentDiscount
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $discount = PaymentDiscount::query()
            ->where('code', $code)
            ->first();

        if (! $discount || ! $this->isApplicable($discount, $user, $price, $course)) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount is not available for the selected plan.',
            ]);
        }

        return $discount;
    }

    public function launchOfferFor(User $user, PaymentPrice $price, ?Course $course = null): ?PaymentDiscount
    {
        return PaymentDiscount::query()
            ->where('is_launch_offer', true)
            ->where('status', GrowthStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function ($query) use ($price): void {
                $query->whereNull('payment_product_id')->orWhere('payment_product_id', $price->payment_product_id);
            })
            ->where(function ($query) use ($price): void {
                $query->whereNull('payment_price_id')->orWhere('payment_price_id', $price->id);
            })
            ->where(function ($query) use ($course): void {
                $query->whereNull('course_id');

                if ($course) {
                    $query->orWhere('course_id', $course->id);
                }
            })
            ->orderByDesc('value')
            ->get()
            ->first(fn (PaymentDiscount $discount): bool => $this->isApplicable($discount, $user, $price, $course));
    }

    public function discountAmount(PaymentDiscount $discount, int $subtotal, string $currency): int
    {
        $subtotal = max(0, $subtotal);

        if ($subtotal === 0) {
            return 0;
        }

        return match ($this->enumValue($discount->getAttribute('type'))) {
            DiscountType::Fixed->value => strtoupper((string) $discount->currency) === strtoupper($currency)
                ? min($subtotal, (int) $discount->value)
                : 0,
            default => (int) floor($subtotal * min(100, max(0, (int) $discount->value)) / 100),
        };
    }

    public function applyToCheckout(PaymentDiscount $discount, PaymentCheckout $checkout, User $user, int $amount): PaymentDiscountRedemption
    {
        $checkout->forceFill([
            'payment_discount_id' => $discount->id,
            'discount_amount' => $amount,
            'custom_data' => $this->mergeGrowthData($checkout, [
                'discount' => [
                    'id' => $discount->id,
                    'code' => $discount->code,
                    'type' => $this->enumValue($discount->getAttribute('type')),
                    'amount' => $amount,
                    'paddle_discount_id' => $discount->paddle_discount_id,
                    'is_launch_offer' => (bool) $discount->is_launch_offer,
                ],
            ]),
        ])->save();

        $redemption = PaymentDiscountRedemption::query()->updateOrCreate(
            ['payment_checkout_id' => $checkout->id, 'payment_discount_id' => $discount->id],
            [
                'user_id' => $user->id,
                'code' => $discount->code,
                'amount' => $amount,
                'metadata' => [
                    'checkout_status' => $this->enumValue($checkout->getAttribute('status')),
                ],
            ],
        );

        $discount->forceFill([
            'redemptions_count' => PaymentDiscountRedemption::query()
                ->where('payment_discount_id', $discount->id)
                ->count(),
        ])->save();

        return $redemption;
    }

    private function isApplicable(PaymentDiscount $discount, User $user, PaymentPrice $price, ?Course $course): bool
    {
        if ($this->enumValue($discount->getAttribute('status')) !== GrowthStatus::Active->value) {
            return false;
        }

        $startsAt = $discount->getAttribute('starts_at');
        $endsAt = $discount->getAttribute('ends_at');

        if ($startsAt instanceof CarbonInterface && $startsAt->isFuture()) {
            return false;
        }

        if ($endsAt instanceof CarbonInterface && $endsAt->isPast()) {
            return false;
        }

        if ($discount->payment_product_id && (int) $discount->payment_product_id !== (int) $price->payment_product_id) {
            return false;
        }

        if ($discount->payment_price_id && (int) $discount->payment_price_id !== (int) $price->id) {
            return false;
        }

        if ($discount->course_id && (! $course || (int) $discount->course_id !== (int) $course->id)) {
            return false;
        }

        if ($discount->max_redemptions !== null && (int) $discount->redemptions_count >= (int) $discount->max_redemptions) {
            return false;
        }

        $limit = max(1, (int) $discount->per_user_limit);
        $existingRedemptions = PaymentDiscountRedemption::query()
            ->where('payment_discount_id', $discount->id)
            ->where('user_id', $user->id)
            ->count();

        return $existingRedemptions < $limit;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeGrowthData(PaymentCheckout $checkout, array $data): array
    {
        $rawCustomData = $checkout->getAttribute('custom_data');
        $customData = is_array($rawCustomData) ? $rawCustomData : [];
        $rawGrowth = $customData['growth'] ?? [];
        $growth = is_array($rawGrowth) ? $rawGrowth : [];

        $customData['growth'] = array_replace_recursive($growth, $data);

        return $customData;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
