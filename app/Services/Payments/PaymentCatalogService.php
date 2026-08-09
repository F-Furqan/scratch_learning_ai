<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaddleClient;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;

class PaymentCatalogService
{
    public function __construct(
        private readonly PaddleClient $paddle,
    ) {}

    public function syncProduct(PaymentProduct $product): PaymentProduct
    {
        if (filled($product->paddle_product_id)) {
            return $product;
        }

        $response = $this->paddle->createProduct($product);
        $product->forceFill([
            'paddle_product_id' => data_get($response, 'data.id'),
        ])->save();

        return $product;
    }

    public function syncPrice(PaymentPrice $price): PaymentPrice
    {
        $this->syncProduct($price->product);

        if (filled($price->paddle_price_id)) {
            return $price;
        }

        $response = $this->paddle->createPrice($price);
        $price->forceFill([
            'paddle_price_id' => data_get($response, 'data.id'),
        ])->save();

        return $price;
    }
}
