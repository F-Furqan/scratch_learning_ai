<?php

namespace App\Services\Payments;

use App\Enums\CoursePurchaseStatus;
use App\Enums\PaymentCheckoutStatus;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentProductType;
use App\Enums\PaymentSubscriptionStatus;
use App\Enums\TeamAccountStatus;
use App\Enums\TeamSeatStatus;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseEnrollment;
use App\Models\CoursePurchase;
use App\Models\PaymentCheckout;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentSubscription;
use App\Models\PaymentWebhookEvent;
use App\Models\TeamAccount;
use App\Models\TeamSeat;
use App\Models\User;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Growth\GrowthFulfillmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaddleWebhookProcessor
{
    public function __construct(
        private readonly PaymentAuditLogger $auditLogger,
        private readonly GrowthFulfillmentService $growth,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function process(PaymentWebhookEvent $event): void
    {
        if ($event->processed_at !== null && $event->status === 'processed') {
            return;
        }

        $event->forceFill([
            'status' => 'processing',
            'attempts' => $event->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            DB::transaction(function () use ($event): void {
                match ($event->event_type) {
                    'transaction.completed',
                    'transaction.paid' => $this->processCompletedTransaction($event),
                    'transaction.canceled',
                    'transaction.payment_failed',
                    'transaction.past_due' => $this->processFailedTransaction($event),
                    'subscription.created',
                    'subscription.activated',
                    'subscription.trialing',
                    'subscription.updated',
                    'subscription.resumed',
                    'subscription.paused',
                    'subscription.past_due',
                    'subscription.canceled' => $this->processSubscription($event),
                    default => $this->recordReconciliation($event, 'webhook', 'skipped', null, null, null, 'No local handler for event type.'),
                };

                $event->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function processCompletedTransaction(PaymentWebhookEvent $event): void
    {
        $payload = $this->payload($event);
        $transactionId = $this->stringValue(data_get($payload, 'data.id'));
        $context = $this->context($payload);
        $checkout = $this->checkout($transactionId, $context);
        $price = $checkout instanceof PaymentCheckout ? $checkout->price : null;
        $price ??= $this->price($context);
        $product = $price instanceof PaymentPrice ? $price->product : null;
        $user = $checkout instanceof PaymentCheckout ? $checkout->user : null;
        $user ??= $this->user($context);
        $team = $checkout instanceof PaymentCheckout ? $checkout->teamAccount : null;
        $team ??= $this->team($context);
        $quantity = max(1, (int) ($context['quantity'] ?? data_get($payload, 'data.items.0.quantity', 1)));
        $subscriptionId = $this->stringValue(data_get($payload, 'data.subscription_id'));

        $order = PaymentOrder::query()->updateOrCreate(
            ['paddle_transaction_id' => $transactionId ?: 'missing-'.$event->event_id],
            [
                'user_id' => $user?->id,
                'team_account_id' => $team?->id,
                'payment_checkout_id' => $checkout?->id,
                'provider' => 'paddle',
                'status' => PaymentOrderStatus::Completed,
                'paddle_customer_id' => $this->stringValue(data_get($payload, 'data.customer_id')),
                'paddle_subscription_id' => $subscriptionId,
                'currency' => $this->currency($payload, $price),
                'subtotal' => $this->money(data_get($payload, 'data.details.totals.subtotal'), $price?->amount * $quantity),
                'tax' => $this->money(data_get($payload, 'data.details.totals.tax'), 0),
                'discount' => $this->money(data_get($payload, 'data.details.totals.discount'), 0),
                'total' => $this->money(data_get($payload, 'data.details.totals.total'), $price?->amount * $quantity),
                'purchased_at' => $this->date(data_get($payload, 'data.billed_at')) ?? now(),
                'payload' => $payload['data'] ?? [],
            ],
        );

        if ($checkout) {
            $checkout->forceFill(['status' => PaymentCheckoutStatus::Completed])->save();
        }

        if ($product) {
            PaymentOrderItem::query()->updateOrCreate(
                [
                    'payment_order_id' => $order->id,
                    'payment_price_id' => $price->id,
                ],
                [
                    'payment_product_id' => $product->id,
                    'course_id' => $product->course_id,
                    'description' => $product->name.' / '.$price->name,
                    'quantity' => $quantity,
                    'unit_amount' => $price->amount,
                    'total' => $price->amount * $quantity,
                    'metadata' => $context,
                ],
            );
        }

        if ($user && $product) {
            $subscription = $subscriptionId ? $this->upsertSubscriptionFromTransaction($payload, $user, $team, $product, $price, $quantity) : null;
            $this->grantEntitlements($user, $product, $order, $subscription, $team, $quantity);
        }

        if ($checkout) {
            $this->growth->completeCheckout($checkout, $order, $context);
            $this->analytics->trackCheckoutCompleted($checkout, $order, $context);
        }

        $this->recordReconciliation($event, 'transaction', 'reconciled', $transactionId, $subscriptionId, $this->stringValue(data_get($payload, 'data.customer_id')));
        $this->auditLogger->log('payment.transaction.completed', $order, $user, null, $order->fresh()?->toArray(), ['event_id' => $event->event_id]);
    }

    private function processFailedTransaction(PaymentWebhookEvent $event): void
    {
        $payload = $this->payload($event);
        $transactionId = $this->stringValue(data_get($payload, 'data.id'));
        $status = match ($event->event_type) {
            'transaction.canceled' => PaymentOrderStatus::Canceled,
            'transaction.past_due' => PaymentOrderStatus::PastDue,
            default => PaymentOrderStatus::Pending,
        };

        if ($transactionId) {
            PaymentOrder::query()
                ->where('paddle_transaction_id', $transactionId)
                ->update(['status' => $status->value, 'payload' => $payload['data'] ?? []]);

            PaymentCheckout::query()
                ->where('paddle_transaction_id', $transactionId)
                ->update(['status' => $status === PaymentOrderStatus::Canceled ? PaymentCheckoutStatus::Canceled->value : PaymentCheckoutStatus::Failed->value]);
        }

        $this->recordReconciliation($event, 'transaction', 'recorded', $transactionId, null, $this->stringValue(data_get($payload, 'data.customer_id')));
    }

    private function processSubscription(PaymentWebhookEvent $event): void
    {
        $payload = $this->payload($event);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $subscriptionId = $this->stringValue($data['id'] ?? null) ?: 'missing-'.$event->event_id;
        $context = $this->context($payload);
        $price = $this->price($context) ?? $this->priceFromSubscriptionItems($data);
        $product = $price?->product;
        $user = $this->user($context);
        $team = $this->team($context);
        $quantity = max(1, (int) ($context['quantity'] ?? data_get($data, 'items.0.quantity', 1)));
        $status = $this->subscriptionStatus((string) ($data['status'] ?? 'inactive'));

        $subscription = PaymentSubscription::query()->updateOrCreate(
            ['paddle_subscription_id' => $subscriptionId],
            [
                'user_id' => $user?->id,
                'team_account_id' => $team?->id,
                'payment_product_id' => $product?->id,
                'payment_price_id' => $price?->id,
                'provider' => 'paddle',
                'status' => $status,
                'paddle_customer_id' => $this->stringValue($data['customer_id'] ?? null),
                'currency' => $this->currency($payload, $price),
                'quantity' => $quantity,
                'current_period_starts_at' => $this->date(data_get($data, 'current_billing_period.starts_at')),
                'current_period_ends_at' => $this->date(data_get($data, 'current_billing_period.ends_at')),
                'trial_ends_at' => $this->date(data_get($data, 'scheduled_change.resume_at')),
                'canceled_at' => $status === PaymentSubscriptionStatus::Canceled->value ? now() : null,
                'next_billed_at' => $this->date(data_get($data, 'next_billed_at')),
                'payload' => $data,
            ],
        );

        if ($team) {
            $team->forceFill([
                'status' => $this->teamStatus($status),
                'seat_limit' => max($team->seat_limit, $quantity),
                'paddle_customer_id' => $this->stringValue($data['customer_id'] ?? null) ?: $team->paddle_customer_id,
                'paddle_subscription_id' => $subscriptionId,
            ])->save();
        }

        if ($user && $product && in_array($status, [PaymentSubscriptionStatus::Active->value, PaymentSubscriptionStatus::Trialing->value], true)) {
            $this->grantEntitlements($user, $product, null, $subscription, $team, $quantity);
        }

        if (in_array($status, [PaymentSubscriptionStatus::Paused->value, PaymentSubscriptionStatus::PastDue->value, PaymentSubscriptionStatus::Inactive->value], true)) {
            $this->revokeSubscriptionEntitlements($subscription);
        }

        $this->recordReconciliation($event, 'subscription', 'reconciled', null, $subscriptionId, $this->stringValue($data['customer_id'] ?? null));
        $this->auditLogger->log('payment.subscription.updated', $subscription, $user, null, $subscription->fresh()?->toArray(), ['event_id' => $event->event_id]);
    }

    private function grantEntitlements(User $user, PaymentProduct $product, ?PaymentOrder $order, ?PaymentSubscription $subscription, ?TeamAccount $team, int $quantity): void
    {
        match ($this->enumValue($product->getAttribute('type'))) {
            PaymentProductType::Course->value => $this->grantCourse($user, $product, $order, $subscription),
            PaymentProductType::PremiumLibrary->value,
            PaymentProductType::Subscription->value => $this->grantTypedEntitlement($user, PaymentEntitlementType::PremiumLibrary, $order, $subscription, $team),
            PaymentProductType::AdFree->value => $this->grantTypedEntitlement($user, PaymentEntitlementType::AdFree, $order, $subscription, $team),
            PaymentProductType::Team->value => $this->grantTeam($user, $order, $subscription, $team, $quantity),
            PaymentProductType::Bundle->value => $this->grantBundle($user, $product, $order, $subscription, $team),
            default => null,
        };
    }

    private function grantCourse(User $user, PaymentProduct $product, ?PaymentOrder $order, ?PaymentSubscription $subscription): void
    {
        if (! $product->course_id) {
            return;
        }

        CoursePurchase::query()->updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $product->course_id],
            [
                'payment_order_id' => $order?->id,
                'source' => 'paddle',
                'status' => CoursePurchaseStatus::Active,
                'purchased_at' => now(),
                'expires_at' => null,
            ],
        );

        CourseEnrollment::query()->updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $product->course_id],
            [
                'source' => 'paddle',
                'status' => 'active',
                'started_at' => now(),
            ],
        );

        $course = Course::query()->find($product->course_id);

        if ($course) {
            $this->upsertEntitlement($user, PaymentEntitlementType::Course, $order, $subscription, null, $course);
        }
    }

    private function grantTeam(User $user, ?PaymentOrder $order, ?PaymentSubscription $subscription, ?TeamAccount $team, int $quantity): void
    {
        $team ??= TeamAccount::query()->firstOrCreate(
            ['owner_id' => $user->id, 'name' => $user->name.' Team'],
            ['seat_limit' => $quantity],
        );

        $team->forceFill([
            'status' => TeamAccountStatus::Active,
            'seat_limit' => max($team->seat_limit, $quantity),
            'paddle_subscription_id' => $subscription?->paddle_subscription_id ?: $team->paddle_subscription_id,
        ])->save();

        TeamSeat::query()->updateOrCreate(
            ['team_account_id' => $team->id, 'email' => $user->email],
            [
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => TeamSeatStatus::Active,
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        );

        $this->upsertEntitlement($user, PaymentEntitlementType::PremiumLibrary, $order, $subscription, $team);
        $this->upsertEntitlement($user, PaymentEntitlementType::TeamSeat, $order, $subscription, $team);
    }

    private function grantTypedEntitlement(User $user, PaymentEntitlementType $type, ?PaymentOrder $order, ?PaymentSubscription $subscription, ?TeamAccount $team): void
    {
        $this->upsertEntitlement($user, $type, $order, $subscription, $team);
    }

    private function grantBundle(User $user, PaymentProduct $product, ?PaymentOrder $order, ?PaymentSubscription $subscription, ?TeamAccount $team): void
    {
        if (! $product->course_bundle_id) {
            $this->grantTypedEntitlement($user, PaymentEntitlementType::Bundle, $order, $subscription, $team);

            return;
        }

        $bundle = CourseBundle::query()
            ->with('courses')
            ->find($product->course_bundle_id);

        if (! $bundle) {
            $this->grantTypedEntitlement($user, PaymentEntitlementType::Bundle, $order, $subscription, $team);

            return;
        }

        $this->upsertEntitlement($user, PaymentEntitlementType::Bundle, $order, $subscription, $team, $bundle);

        if (! $team) {
            $bundle->students()->syncWithoutDetaching([
                $user->id => [
                    'status' => 'active',
                    'started_at' => now(),
                ],
            ]);
        }

        foreach ($bundle->courses as $course) {
            if (! $team) {
                CoursePurchase::query()->updateOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    [
                        'payment_order_id' => $order?->id,
                        'source' => 'paddle_bundle',
                        'status' => CoursePurchaseStatus::Active,
                        'purchased_at' => now(),
                        'expires_at' => null,
                    ],
                );

                CourseEnrollment::query()->updateOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    [
                        'source' => 'paddle_bundle',
                        'status' => 'active',
                        'started_at' => now(),
                    ],
                );
            }

            $this->upsertEntitlement($user, PaymentEntitlementType::Course, $order, $subscription, $team, $course);
        }
    }

    private function upsertEntitlement(User $user, PaymentEntitlementType $type, ?PaymentOrder $order, ?PaymentSubscription $subscription, ?TeamAccount $team = null, ?Model $entitlementable = null): PaymentEntitlement
    {
        return PaymentEntitlement::query()->updateOrCreate(
            [
                'user_id' => $team ? null : $user->id,
                'team_account_id' => $team?->id,
                'type' => $type->value,
                'entitlementable_type' => $entitlementable?->getMorphClass(),
                'entitlementable_id' => $entitlementable?->getKey(),
            ],
            [
                'payment_order_id' => $order?->id,
                'payment_subscription_id' => $subscription?->id,
                'status' => PaymentEntitlementStatus::Active,
                'source' => 'paddle',
                'starts_at' => now(),
                'ends_at' => $subscription?->current_period_ends_at,
                'metadata' => [
                    'paddle_subscription_id' => $subscription?->paddle_subscription_id,
                    'paddle_transaction_id' => $order?->paddle_transaction_id,
                ],
            ],
        );
    }

    private function revokeSubscriptionEntitlements(PaymentSubscription $subscription): void
    {
        PaymentEntitlement::query()
            ->where('payment_subscription_id', $subscription->id)
            ->update([
                'status' => PaymentEntitlementStatus::Revoked->value,
                'ends_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PaymentWebhookEvent $event): array
    {
        $payload = $event->getAttribute('payload');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function context(array $payload): array
    {
        $customData = data_get($payload, 'data.custom_data');

        return is_array($customData) ? $customData : [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkout(?string $transactionId, array $context): ?PaymentCheckout
    {
        $checkoutId = $context['checkout_id'] ?? null;

        if (! $transactionId && ! is_numeric($checkoutId)) {
            return null;
        }

        $query = PaymentCheckout::query()->with(['user', 'price.product.course', 'price.product.bundle.courses', 'teamAccount']);

        if ($transactionId) {
            return $query->where('paddle_transaction_id', $transactionId)->first();
        }

        return $query->whereKey((int) $checkoutId)->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function price(array $context): ?PaymentPrice
    {
        $priceId = $context['payment_price_id'] ?? null;

        return is_numeric($priceId)
            ? PaymentPrice::query()->with(['product.course', 'product.bundle.courses'])->find((int) $priceId)
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function priceFromSubscriptionItems(array $data): ?PaymentPrice
    {
        $paddlePriceId = data_get($data, 'items.0.price.id');

        return is_string($paddlePriceId)
            ? PaymentPrice::query()->with(['product.course', 'product.bundle.courses'])->where('paddle_price_id', $paddlePriceId)->first()
            : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function user(array $context): ?User
    {
        $userId = $context['user_id'] ?? null;

        return is_numeric($userId) ? User::query()->find((int) $userId) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function team(array $context): ?TeamAccount
    {
        $teamId = $context['team_account_id'] ?? null;

        return is_numeric($teamId) ? TeamAccount::query()->find((int) $teamId) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertSubscriptionFromTransaction(array $payload, User $user, ?TeamAccount $team, PaymentProduct $product, ?PaymentPrice $price, int $quantity): ?PaymentSubscription
    {
        $subscriptionId = $this->stringValue(data_get($payload, 'data.subscription_id'));

        if (! $subscriptionId) {
            return null;
        }

        return PaymentSubscription::query()->updateOrCreate(
            ['paddle_subscription_id' => $subscriptionId],
            [
                'user_id' => $user->id,
                'team_account_id' => $team?->id,
                'payment_product_id' => $product->id,
                'payment_price_id' => $price?->id,
                'provider' => 'paddle',
                'status' => PaymentSubscriptionStatus::Active,
                'paddle_customer_id' => $this->stringValue(data_get($payload, 'data.customer_id')),
                'currency' => $this->currency($payload, $price),
                'quantity' => $quantity,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => null,
                'payload' => data_get($payload, 'data', []),
            ],
        );
    }

    private function subscriptionStatus(string $status): string
    {
        return match ($status) {
            'active' => PaymentSubscriptionStatus::Active->value,
            'trialing' => PaymentSubscriptionStatus::Trialing->value,
            'past_due' => PaymentSubscriptionStatus::PastDue->value,
            'paused' => PaymentSubscriptionStatus::Paused->value,
            'canceled' => PaymentSubscriptionStatus::Canceled->value,
            default => PaymentSubscriptionStatus::Inactive->value,
        };
    }

    private function teamStatus(string $subscriptionStatus): string
    {
        return match ($subscriptionStatus) {
            PaymentSubscriptionStatus::Active->value => TeamAccountStatus::Active->value,
            PaymentSubscriptionStatus::Trialing->value => TeamAccountStatus::Trialing->value,
            PaymentSubscriptionStatus::PastDue->value => TeamAccountStatus::PastDue->value,
            PaymentSubscriptionStatus::Canceled->value => TeamAccountStatus::Canceled->value,
            default => TeamAccountStatus::Inactive->value,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function currency(array $payload, ?PaymentPrice $price): string
    {
        $currency = data_get($payload, 'data.currency_code') ?: data_get($payload, 'data.details.totals.currency_code') ?: $price?->currency ?: 'USD';

        return strtoupper((string) $currency);
    }

    private function money(mixed $value, ?int $fallback): int
    {
        return is_numeric($value) ? (int) $value : (int) ($fallback ?? 0);
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && filled($value) ? Carbon::parse($value) : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? $value : null;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private function recordReconciliation(PaymentWebhookEvent $event, string $type, string $status, ?string $transactionId, ?string $subscriptionId, ?string $customerId, ?string $notes = null): void
    {
        PaymentReconciliationRecord::query()->create([
            'provider' => 'paddle',
            'event_id' => $event->event_id,
            'record_type' => $type,
            'status' => $status,
            'paddle_transaction_id' => $transactionId,
            'paddle_subscription_id' => $subscriptionId,
            'paddle_customer_id' => $customerId,
            'payload' => $event->payload,
            'reconciled_at' => $status === 'reconciled' ? now() : null,
            'notes' => $notes,
        ]);
    }
}
