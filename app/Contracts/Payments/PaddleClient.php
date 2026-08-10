<?php

namespace App\Contracts\Payments;

use App\Models\PaymentCheckout;
use App\Models\PaymentOrder;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;

interface PaddleClient
{
    /**
     * @return array<string, mixed>
     */
    public function createProduct(PaymentProduct $product): array;

    /**
     * @return array<string, mixed>
     */
    public function createPrice(PaymentPrice $price): array;

    /**
     * @return array<string, mixed>
     */
    public function createCheckoutTransaction(PaymentCheckout $checkout): array;

    /**
     * @return array<string, mixed>
     */
    public function refundTransaction(PaymentOrder $order, string $reason): array;
}
