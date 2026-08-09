<?php

namespace App\Services\Growth;

use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Models\PaymentPrice;
use Illuminate\Support\Collection;

class GrowthOfferService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function membershipUpsells(): array
    {
        return PaymentPrice::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query
                ->whereIn('type', [PaymentProductType::PremiumLibrary->value, PaymentProductType::Subscription->value])
                ->where('status', PaymentProductStatus::Active->value))
            ->with('product')
            ->orderByRaw("case billing_interval when 'month' then 1 when 'year' then 2 else 3 end")
            ->limit(4)
            ->get()
            ->map(fn (PaymentPrice $price): array => $this->pricePayload($price))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function premiumLibraryPrices(): array
    {
        return PaymentPrice::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query
                ->where('type', PaymentProductType::PremiumLibrary->value)
                ->where('status', PaymentProductStatus::Active->value))
            ->with('product')
            ->get()
            ->map(fn (PaymentPrice $price): array => $this->pricePayload($price))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PaymentPrice>  $prices
     * @return array<int, array<string, mixed>>
     */
    public function monthlyYearlyUpsells(Collection $prices): array
    {
        return $prices
            ->filter(fn (PaymentPrice $price): bool => in_array($this->enumValue($price->billing_interval), [
                PaymentBillingInterval::Month->value,
                PaymentBillingInterval::Year->value,
            ], true))
            ->map(fn (PaymentPrice $price): array => $this->pricePayload($price))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pricePayload(PaymentPrice $price): array
    {
        return [
            'id' => $price->id,
            'product_id' => $price->payment_product_id,
            'product_name' => $price->product?->name,
            'name' => $price->name,
            'billing_interval' => $this->enumValue($price->billing_interval),
            'amount' => $price->amount,
            'formatted_amount' => $price->formattedAmount(),
            'checkout_url' => route('checkout.prices.store', $price->id),
        ];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
