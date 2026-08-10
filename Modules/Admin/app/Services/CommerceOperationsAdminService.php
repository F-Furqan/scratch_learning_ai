<?php

namespace Modules\Admin\Services;

use App\Enums\CoursePurchaseStatus;
use App\Enums\PaymentCheckoutStatus;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentSubscriptionStatus;
use App\Models\CoursePurchase;
use App\Models\PaymentAuditLog;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscountRedemption;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentSubscription;
use App\Models\PaymentWebhookEvent;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class CommerceOperationsAdminService
{
    /** @var list<string> */
    public const RESOURCES = [
        'payment_checkouts',
        'payment_orders',
        'payment_subscriptions',
        'payment_reconciliation',
        'course_purchases',
        'payment_entitlements',
        'payment_order_items',
        'discount_redemptions',
        'payment_webhook_events',
        'payment_audit_logs',
    ];

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function readOnly(string $resource): bool
    {
        return $this->supports($resource);
    }

    public function exportable(string $resource): bool
    {
        return $this->supports($resource);
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'payment_checkouts' => ['user', 'product', 'status', 'quantity', 'amount', 'transaction', 'expires_at'],
            'payment_orders' => ['user', 'status', 'total', 'transaction', 'subscription', 'purchased_at'],
            'payment_subscriptions' => ['user', 'product', 'status', 'quantity', 'subscription', 'next_billed_at'],
            'payment_reconciliation' => ['record_type', 'status', 'event_id', 'transaction', 'subscription', 'reconciled_at'],
            'course_purchases' => ['student', 'course', 'status', 'source', 'order', 'purchased_at', 'expires_at'],
            'payment_entitlements' => ['recipient', 'entitlement', 'type', 'status', 'source', 'order', 'ends_at'],
            'payment_order_items' => ['order', 'customer', 'description', 'course', 'quantity', 'unit_amount', 'total'],
            'discount_redemptions' => ['code', 'discount', 'customer', 'order', 'amount', 'redeemed_at'],
            'payment_webhook_events' => ['event_id', 'event_type', 'status', 'attempts', 'queued_at', 'processed_at', 'last_error'],
            'payment_audit_logs' => ['action', 'actor', 'customer', 'record', 'ip_address', 'created_at'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /** @return array<int, array<string, mixed>> */
    public function fields(string $resource): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];
        $statuses = match ($resource) {
            'payment_checkouts' => $this->enumOptions(PaymentCheckoutStatus::cases()),
            'payment_orders' => $this->enumOptions(PaymentOrderStatus::cases()),
            'payment_subscriptions' => $this->enumOptions(PaymentSubscriptionStatus::cases()),
            'course_purchases' => $this->enumOptions(CoursePurchaseStatus::cases()),
            'payment_entitlements' => $this->enumOptions(PaymentEntitlementStatus::cases()),
            'payment_webhook_events' => $this->valueOptions(['accepted', 'queued', 'processing', 'processed', 'failed']),
            'payment_reconciliation' => $this->valueOptions(['pending', 'reconciled', 'recorded', 'failed', 'approved', 'pending_approval']),
            default => [],
        };

        if ($statuses !== []) {
            $filters[] = $this->field('status', 'Status', 'select', $statuses);
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkActions(string $resource): array
    {
        return [];
    }

    /** @return array<string, int> */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'payment_checkouts' => ['ready' => PaymentCheckout::query()->where('status', PaymentCheckoutStatus::Ready->value)->count(), 'completed' => PaymentCheckout::query()->where('status', PaymentCheckoutStatus::Completed->value)->count()],
            'payment_orders' => ['completed' => PaymentOrder::query()->where('status', PaymentOrderStatus::Completed->value)->count(), 'refunded' => PaymentOrder::query()->where('status', PaymentOrderStatus::Refunded->value)->count()],
            'payment_subscriptions' => ['active' => PaymentSubscription::query()->where('status', PaymentSubscriptionStatus::Active->value)->count(), 'past_due' => PaymentSubscription::query()->where('status', PaymentSubscriptionStatus::PastDue->value)->count()],
            'payment_reconciliation' => ['reconciled' => PaymentReconciliationRecord::query()->where('status', 'reconciled')->count(), 'pending' => PaymentReconciliationRecord::query()->where('status', 'pending')->count()],
            'course_purchases' => ['active' => CoursePurchase::query()->where('status', CoursePurchaseStatus::Active->value)->count(), 'revoked' => CoursePurchase::query()->whereIn('status', [CoursePurchaseStatus::Revoked->value, CoursePurchaseStatus::Refunded->value])->count()],
            'payment_entitlements' => ['active' => PaymentEntitlement::query()->where('status', PaymentEntitlementStatus::Active->value)->count(), 'revoked' => PaymentEntitlement::query()->where('status', PaymentEntitlementStatus::Revoked->value)->count()],
            'payment_order_items' => ['items' => PaymentOrderItem::query()->count(), 'orders' => PaymentOrderItem::query()->distinct()->count('payment_order_id')],
            'discount_redemptions' => ['redemptions' => PaymentDiscountRedemption::query()->count(), 'customers' => PaymentDiscountRedemption::query()->whereNotNull('user_id')->distinct()->count('user_id')],
            'payment_webhook_events' => ['failed' => PaymentWebhookEvent::query()->where('status', 'failed')->count(), 'processed' => PaymentWebhookEvent::query()->where('status', 'processed')->count()],
            'payment_audit_logs' => ['events' => PaymentAuditLog::query()->count(), 'actors' => PaymentAuditLog::query()->whereNotNull('actor_id')->distinct()->count('actor_id')],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'payment_checkouts' => $this->checkoutRow($record),
            'payment_orders' => $this->orderRow($record),
            'payment_subscriptions' => $this->subscriptionRow($record),
            'payment_reconciliation' => $this->reconciliationRow($record),
            'course_purchases' => $this->purchaseRow($record),
            'payment_entitlements' => $this->entitlementRow($record),
            'payment_order_items' => $this->orderItemRow($record),
            'discount_redemptions' => $this->redemptionRow($record),
            'payment_webhook_events' => $this->webhookRow($record),
            'payment_audit_logs' => $this->auditRow($record),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function checkoutRow(Model $record): array
    {
        abort_unless($record instanceof PaymentCheckout, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'product' => $record->price?->product?->name,
            'status' => $this->enumValue($record->status),
            'quantity' => $record->quantity,
            'amount' => $this->money($record->price?->currency, ((int) $record->price?->amount) * $record->quantity),
            'transaction' => $record->paddle_transaction_id,
            'expires_at' => $this->dateTime($record->expires_at),
        ];
    }

    /** @return array<string, mixed> */
    private function orderRow(Model $record): array
    {
        abort_unless($record instanceof PaymentOrder, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'status' => $this->enumValue($record->status),
            'total' => $this->money($record->currency, $record->total),
            'transaction' => $record->paddle_transaction_id,
            'subscription' => $record->paddle_subscription_id,
            'purchased_at' => $this->dateTime($record->purchased_at),
            'workflow_actions' => [$this->manageAction('payment-order', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(Model $record): array
    {
        abort_unless($record instanceof PaymentSubscription, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'product' => $record->product?->name,
            'status' => $this->enumValue($record->status),
            'quantity' => $record->quantity,
            'subscription' => $record->paddle_subscription_id,
            'next_billed_at' => $this->dateTime($record->next_billed_at),
        ];
    }

    /** @return array<string, mixed> */
    private function reconciliationRow(Model $record): array
    {
        abort_unless($record instanceof PaymentReconciliationRecord, 500);

        return $this->baseRow($record) + [
            'record_type' => $record->record_type,
            'status' => $record->status,
            'event_id' => $record->event_id,
            'transaction' => $record->paddle_transaction_id,
            'subscription' => $record->paddle_subscription_id,
            'reconciled_at' => $this->dateTime($record->reconciled_at),
            'workflow_actions' => [$this->manageAction('reconciliation', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseRow(Model $record): array
    {
        abort_unless($record instanceof CoursePurchase, 500);

        return $this->baseRow($record) + [
            'student' => $record->user?->email,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->status),
            'source' => $record->source,
            'order' => $record->order?->paddle_transaction_id,
            'purchased_at' => $this->dateTime($record->purchased_at),
            'expires_at' => $this->dateTime($record->expires_at),
            'workflow_actions' => [$this->manageAction('course-purchase', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function entitlementRow(Model $record): array
    {
        abort_unless($record instanceof PaymentEntitlement, 500);

        return $this->baseRow($record) + [
            'recipient' => $record->user?->email ?? $record->teamAccount?->name,
            'entitlement' => $this->modelLabel($record->entitlementable),
            'type' => $this->enumValue($record->type),
            'status' => $this->enumValue($record->status),
            'source' => $record->source,
            'order' => $record->order?->paddle_transaction_id,
            'ends_at' => $this->dateTime($record->ends_at),
            'workflow_actions' => [$this->manageAction('entitlement', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function orderItemRow(Model $record): array
    {
        abort_unless($record instanceof PaymentOrderItem, 500);

        return $this->baseRow($record) + [
            'order' => $record->order?->paddle_transaction_id ?: '#'.$record->payment_order_id,
            'customer' => $record->order?->user?->email,
            'description' => $record->description,
            'course' => $record->course?->title,
            'quantity' => $record->quantity,
            'unit_amount' => $this->money($record->order?->currency, $record->unit_amount),
            'total' => $this->money($record->order?->currency, $record->total),
        ];
    }

    /** @return array<string, mixed> */
    private function redemptionRow(Model $record): array
    {
        abort_unless($record instanceof PaymentDiscountRedemption, 500);

        return $this->baseRow($record) + [
            'code' => $record->code,
            'discount' => $record->discount?->name,
            'customer' => $record->user?->email,
            'order' => $record->order?->paddle_transaction_id,
            'amount' => $this->money($record->order?->currency, $record->amount),
            'redeemed_at' => $this->dateTime($record->redeemed_at),
        ];
    }

    /** @return array<string, mixed> */
    private function webhookRow(Model $record): array
    {
        abort_unless($record instanceof PaymentWebhookEvent, 500);

        return $this->baseRow($record) + [
            'event_id' => $record->event_id,
            'event_type' => $record->event_type,
            'status' => $record->status,
            'attempts' => $record->attempts,
            'queued_at' => $this->dateTime($record->queued_at),
            'processed_at' => $this->dateTime($record->processed_at),
            'last_error' => $record->last_error,
            'workflow_actions' => [$this->manageAction('webhook-event', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function auditRow(Model $record): array
    {
        abort_unless($record instanceof PaymentAuditLog, 500);

        return $this->baseRow($record) + [
            'action' => $record->action,
            'actor' => $record->actor?->email,
            'customer' => $record->user?->email,
            'record' => $this->modelLabel($record->auditable),
            'ip_address' => $record->ip_address,
            'created_at' => $this->dateTime($record->created_at),
        ];
    }

    /** @return array{id: mixed, form: array<mixed>} */
    private function baseRow(Model $record): array
    {
        return ['id' => $record->getKey(), 'form' => []];
    }

    /** @return array{label: string, url: string, tone: string, method: string} */
    private function manageAction(string $type, Model $record): array
    {
        return [
            'label' => 'Manage',
            'url' => route('admin.operational-records.show', ['type' => $type, 'id' => $record->getKey()], false),
            'tone' => 'default',
            'method' => 'get',
        ];
    }

    /** @param array<int, BackedEnum> $cases @return array<int, array{label: string, value: string}> */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => ['label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(), 'value' => (string) $case->value], $cases);
    }

    /** @param list<string> $values @return array<int, array{label: string, value: string}> */
    private function valueOptions(array $values): array
    {
        return array_map(fn (string $value): array => ['label' => str($value)->replace('_', ' ')->headline()->toString(), 'value' => $value], $values);
    }

    /** @param array<int, array{label: string, value: string|int}> $options @return array<string, mixed> */
    private function field(string $key, string $label, string $type, array $options = []): array
    {
        return compact('key', 'label', 'type', 'options') + ['required' => false];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateTimeString() : null;
    }

    private function money(?string $currency, int $amount): string
    {
        return strtoupper($currency ?: 'USD').' '.number_format($amount / 100, 2);
    }

    private function modelLabel(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return (string) ($model->getAttribute('title')
            ?? $model->getAttribute('name')
            ?? $model->getAttribute('email')
            ?? class_basename($model).' #'.$model->getKey());
    }
}
