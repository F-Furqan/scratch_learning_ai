<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaddleClient;
use App\Enums\PaymentBillingInterval;
use App\Models\PaymentCheckout;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaddleBillingClient implements PaddleClient
{
    /**
     * @return array<string, mixed>
     */
    public function createProduct(PaymentProduct $product): array
    {
        return $this->post('/products', [
            'name' => $product->name,
            'description' => $product->description,
            'tax_category' => $product->tax_category,
            'custom_data' => [
                'local_product_id' => $product->id,
                'product_type' => $this->enumValue($product->getAttribute('type')),
                'course_id' => $product->course_id,
                'course_bundle_id' => $product->course_bundle_id,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createPrice(PaymentPrice $price): array
    {
        $product = $price->product;

        if (blank($product?->paddle_product_id)) {
            throw new RuntimeException('Payment product must be synced with Paddle before creating a price.');
        }

        return $this->post('/prices', array_filter([
            'product_id' => $product->paddle_product_id,
            'name' => $price->name,
            'description' => $price->name,
            'billing_cycle' => $this->billingCycle($price),
            'trial_period' => $price->trial_days ? ['interval' => 'day', 'frequency' => $price->trial_days] : null,
            'tax_mode' => 'account_setting',
            'unit_price' => [
                'amount' => (string) $price->amount,
                'currency_code' => strtoupper($price->currency),
            ],
            'quantity' => [
                'minimum' => $price->seat_min ?: 1,
                'maximum' => $price->seat_max ?: 100,
            ],
            'custom_data' => [
                'local_price_id' => $price->id,
                'local_product_id' => $product->id,
                'product_type' => $this->enumValue($product->getAttribute('type')),
                'course_id' => $product->course_id,
                'course_bundle_id' => $product->course_bundle_id,
            ],
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function createCheckoutTransaction(PaymentCheckout $checkout): array
    {
        $price = $checkout->price;

        if (blank($price?->paddle_price_id)) {
            throw new RuntimeException('Payment price must be synced with Paddle before checkout creation.');
        }

        $discountId = data_get($checkout->custom_data, 'growth.discount.paddle_discount_id')
            ?: $checkout->discount?->paddle_discount_id;

        return $this->post('/transactions?include=checkout', array_filter([
            'collection_mode' => 'automatic',
            'items' => [
                [
                    'price_id' => $price->paddle_price_id,
                    'quantity' => max(1, $checkout->quantity),
                ],
            ],
            'discount_id' => is_string($discountId) && filled($discountId) ? $discountId : null,
            'custom_data' => $checkout->custom_data,
            'checkout' => [
                'url' => config('payments.paddle.checkout_success_url'),
            ],
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function billingCycle(PaymentPrice $price): ?array
    {
        return match ($this->enumValue($price->getAttribute('billing_interval'))) {
            PaymentBillingInterval::Month->value => ['interval' => 'month', 'frequency' => 1],
            PaymentBillingInterval::Year->value => ['interval' => 'year', 'frequency' => 1],
            default => null,
        };
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($this->baseUrl().$path, $payload)->throw()->json();

        return is_array($response) ? $response : [];
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('payments.paddle.api_key', '');

        if (blank($apiKey)) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout(15)
            ->retry(2, 250);
    }

    private function baseUrl(): string
    {
        $environment = (string) config('payments.paddle.environment', 'sandbox');

        return rtrim((string) config("payments.paddle.{$environment}_api_url"), '/');
    }
}
