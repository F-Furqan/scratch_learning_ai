<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaddleClient;
use App\Enums\PaymentCheckoutStatus;
use App\Enums\PaymentProductType;
use App\Models\Course;
use App\Models\PaymentCheckout;
use App\Models\PaymentPrice;
use App\Models\TeamAccount;
use App\Models\User;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Growth\GrowthCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentCheckoutService
{
    public function __construct(
        private readonly PaddleClient $paddle,
        private readonly PaymentAuditLogger $auditLogger,
        private readonly GrowthCheckoutService $growth,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function create(User $user, PaymentPrice $price, int $quantity = 1, ?Course $course = null, ?TeamAccount $teamAccount = null, ?Request $request = null): PaymentCheckout
    {
        $price->loadMissing(['product.course', 'product.bundle']);
        $product = $price->product;

        if (! $price->is_active || ! $product->isActive()) {
            throw ValidationException::withMessages([
                'price' => 'This payment plan is not available.',
            ]);
        }

        if (blank($price->paddle_price_id)) {
            throw ValidationException::withMessages([
                'price' => 'This payment plan is not connected to Paddle.',
            ]);
        }

        $productType = $this->enumValue($product->getAttribute('type'));

        if ($productType === PaymentProductType::Course->value) {
            $course = $product->course;
            $quantity = 1;
        }

        if ($productType === PaymentProductType::Team->value) {
            $quantity = $this->validatedSeatQuantity($price, $quantity);
            $teamAccount ??= $this->teamForCheckout($user, $quantity);
        }

        return DB::transaction(function () use ($user, $price, $product, $productType, $quantity, $course, $teamAccount, $request): PaymentCheckout {
            $checkout = PaymentCheckout::query()->create([
                'user_id' => $user->id,
                'payment_price_id' => $price->id,
                'course_id' => $course?->id,
                'team_account_id' => $teamAccount?->id,
                'quantity' => $quantity,
                'status' => PaymentCheckoutStatus::Initiated,
                'custom_data' => [],
                'expires_at' => Carbon::now()->addHour(),
            ]);

            $checkout->forceFill([
                'custom_data' => [
                    'checkout_id' => $checkout->id,
                    'user_id' => $user->id,
                    'payment_price_id' => $price->id,
                    'payment_product_id' => $product->id,
                    'product_type' => $productType,
                    'course_id' => $course?->id,
                    'team_account_id' => $teamAccount?->id,
                    'quantity' => $quantity,
                ],
            ])->save();

            $checkout = $this->growth->apply($checkout, $user, $price, $course, $request);
            $freshCheckout = $checkout->fresh(['price.product', 'discount']);

            if (! $freshCheckout instanceof PaymentCheckout) {
                throw new RuntimeException('Payment checkout could not be reloaded before Paddle transaction creation.');
            }

            $response = $this->paddle->createCheckoutTransaction($freshCheckout);
            $transactionId = data_get($response, 'data.id');
            $checkoutUrl = data_get($response, 'data.checkout.url');

            $checkout->forceFill([
                'status' => PaymentCheckoutStatus::Ready,
                'paddle_transaction_id' => is_string($transactionId) ? $transactionId : null,
                'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
            ])->save();

            $this->auditLogger->log('payment.checkout.created', $checkout, $user, null, $checkout->fresh()?->toArray(), [
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'paddle_transaction_id' => $checkout->paddle_transaction_id,
            ], $request);

            $this->analytics->trackCheckoutStarted($checkout, $request);

            return $checkout;
        });
    }

    private function validatedSeatQuantity(PaymentPrice $price, int $quantity): int
    {
        $quantity = max(1, $quantity);

        if ($price->seat_min && $quantity < $price->seat_min) {
            throw ValidationException::withMessages([
                'quantity' => "This team plan requires at least {$price->seat_min} seats.",
            ]);
        }

        if ($price->seat_max && $quantity > $price->seat_max) {
            throw ValidationException::withMessages([
                'quantity' => "This team plan allows at most {$price->seat_max} seats.",
            ]);
        }

        return $quantity;
    }

    private function teamForCheckout(User $user, int $seatLimit): TeamAccount
    {
        return TeamAccount::query()->firstOrCreate(
            ['owner_id' => $user->id, 'name' => $user->name.' Team'],
            ['seat_limit' => $seatLimit],
        );
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
