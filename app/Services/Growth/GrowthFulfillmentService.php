<?php

namespace App\Services\Growth;

use App\Enums\CheckoutRecoveryStatus;
use App\Enums\GiftPurchaseStatus;
use App\Enums\ReferralConversionStatus;
use App\Models\AbAssignment;
use App\Models\GiftPurchase;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscountRedemption;
use App\Models\PaymentOrder;
use App\Models\ReferralConversion;

class GrowthFulfillmentService
{
    public function __construct(
        private readonly CheckoutRecoveryService $recoveries,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function completeCheckout(PaymentCheckout $checkout, PaymentOrder $order, array $context): void
    {
        $checkout->loadMissing(['recovery', 'giftPurchase']);
        $this->recoveries->markRecovered($checkout);

        PaymentDiscountRedemption::query()
            ->where('payment_checkout_id', $checkout->id)
            ->update([
                'payment_order_id' => $order->id,
                'redeemed_at' => now(),
                'updated_at' => now(),
            ]);

        GiftPurchase::query()
            ->where('payment_checkout_id', $checkout->id)
            ->update([
                'payment_order_id' => $order->id,
                'status' => GiftPurchaseStatus::Purchased->value,
                'purchased_at' => now(),
                'updated_at' => now(),
            ]);

        $this->completeReferral($checkout, $order);
        $this->completeExperiment($context);
    }

    private function completeReferral(PaymentCheckout $checkout, PaymentOrder $order): void
    {
        $conversions = ReferralConversion::query()
            ->where('payment_checkout_id', $checkout->id)
            ->with('partner')
            ->get();

        foreach ($conversions as $conversion) {
            $rate = max(0, (int) $conversion->partner?->commission_rate_basis_points);
            $rawMetadata = $conversion->getAttribute('metadata');
            $metadata = is_array($rawMetadata) ? $rawMetadata : [];

            $conversion->forceFill([
                'payment_order_id' => $order->id,
                'status' => ReferralConversionStatus::Approved,
                'amount' => $order->total,
                'commission_amount' => (int) floor($order->total * $rate / 10_000),
                'converted_at' => now(),
                'metadata' => array_replace($metadata, [
                    'order_currency' => $order->currency,
                    'checkout_status' => CheckoutRecoveryStatus::Recovered->value,
                ]),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function completeExperiment(array $context): void
    {
        $assignmentId = data_get($context, 'growth.ab_test.assignment_id');

        if (! is_numeric($assignmentId)) {
            return;
        }

        $assignment = AbAssignment::query()->with('variant')->find((int) $assignmentId);

        if (! $assignment || $assignment->converted_at) {
            return;
        }

        $assignment->forceFill(['converted_at' => now()])->save();
        $assignment->variant?->increment('conversions_count');
    }
}
