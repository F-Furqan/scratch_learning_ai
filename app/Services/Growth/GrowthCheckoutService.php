<?php

namespace App\Services\Growth;

use App\Models\Course;
use App\Models\GiftPurchase;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentPrice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GrowthCheckoutService
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly AffiliateTrackingService $affiliates,
        private readonly CheckoutRecoveryService $recoveries,
        private readonly AbTestingService $experiments,
    ) {}

    public function apply(PaymentCheckout $checkout, User $user, PaymentPrice $price, ?Course $course = null, ?Request $request = null): PaymentCheckout
    {
        if (! $request) {
            return $checkout;
        }

        $price->loadMissing('product');
        $product = $price->product;
        $growth = [];

        $discount = $this->discountFromRequest($request, $user, $price, $course);

        if ($discount) {
            $subtotal = (int) $price->amount * max(1, (int) $checkout->quantity);
            $amount = $this->discounts->discountAmount($discount, $subtotal, (string) $price->currency);
            $redemption = $this->discounts->applyToCheckout($discount, $checkout, $user, $amount);
            $growth['discount_redemption_id'] = $redemption->id;
        }

        $gift = $this->giftFromRequest($checkout, $user, $price, $course, $request);

        if ($gift) {
            $growth['gift'] = [
                'id' => $gift->id,
                'code' => $gift->code,
                'recipient_email' => $gift->recipient_email,
            ];
        }

        $recovery = $this->recoveries->createForCheckout(
            $checkout,
            $user,
            $request->string('recovery_email')->toString() ?: $request->string('gift_recipient_email')->toString() ?: null,
            $request,
        );

        $growth['recovery'] = [
            'id' => $recovery->id,
            'token' => $recovery->recovery_token,
        ];

        $visitorId = $this->affiliates->visitorId($request);
        $affiliateCode = $request->string('affiliate_code')->toString();
        $cookieAffiliateCode = $request->cookie(AffiliateTrackingService::AFFILIATE_COOKIE);

        if (blank($affiliateCode) && is_string($cookieAffiliateCode)) {
            $affiliateCode = $cookieAffiliateCode;
        }

        $conversion = $this->affiliates->conversionForCheckout($checkout, $affiliateCode, $visitorId, $user);

        if ($conversion) {
            $growth['affiliate'] = [
                'conversion_id' => $conversion->id,
                'partner_id' => $conversion->affiliate_partner_id,
                'visitor_id' => $visitorId,
            ];
        }

        $experimentKey = $request->string('ab_experiment_key')->toString() ?: 'pricing_cta';
        $assignment = $this->experiments->assign($experimentKey, $request, $user);

        if ($assignment) {
            $growth['ab_test'] = [
                'assignment_id' => $assignment->id,
                'experiment_id' => $assignment->ab_experiment_id,
                'variant_id' => $assignment->ab_variant_id,
                'visitor_id' => $assignment->visitor_id,
            ];
        }

        $checkout->forceFill([
            'custom_data' => $this->mergeGrowthData($checkout, $growth),
        ])->save();

        return $checkout->fresh(['price.product', 'discount']) ?? $checkout;
    }

    private function discountFromRequest(Request $request, User $user, PaymentPrice $price, ?Course $course): ?PaymentDiscount
    {
        $code = $request->string('discount_code')->toString()
            ?: $request->string('coupon')->toString();

        return filled($code)
            ? $this->discounts->resolve($code, $user, $price, $course)
            : $this->discounts->launchOfferFor($user, $price, $course);
    }

    private function giftFromRequest(PaymentCheckout $checkout, User $user, PaymentPrice $price, ?Course $course, Request $request): ?GiftPurchase
    {
        $recipientEmail = $request->string('gift_recipient_email')->toString();

        if (blank($recipientEmail)) {
            return null;
        }

        $price->loadMissing('product.bundle');
        $product = $price->product;

        return GiftPurchase::query()->updateOrCreate(
            ['payment_checkout_id' => $checkout->id],
            [
                'purchaser_id' => $user->id,
                'course_id' => $course?->id ?: $product?->course_id,
                'course_bundle_id' => $product?->course_bundle_id,
                'payment_product_id' => $product?->id,
                'payment_price_id' => $price->id,
                'recipient_email' => strtolower($recipientEmail),
                'recipient_name' => $request->string('gift_recipient_name')->toString() ?: null,
                'code' => Str::upper(Str::random(12)),
                'message' => $request->string('gift_message')->toString() ?: null,
                'expires_at' => now()->addYear(),
                'metadata' => [
                    'checkout_id' => $checkout->id,
                ],
            ],
        );
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
}
