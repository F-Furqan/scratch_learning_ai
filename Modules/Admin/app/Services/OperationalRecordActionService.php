<?php

namespace Modules\Admin\Services;

use App\Contracts\Payments\PaddleClient;
use App\Enums\CommunityContentStatus;
use App\Enums\CoursePurchaseStatus;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\PublishStatus;
use App\Enums\ReputationEventType;
use App\Jobs\ProcessPaddleWebhookEventJob;
use App\Jobs\ReconcilePaymentRecordJob;
use App\Models\AuditLog;
use App\Models\CommunityGroupMember;
use App\Models\CommunityReputationScore;
use App\Models\Course;
use App\Models\CoursePurchase;
use App\Models\CourseQuestion;
use App\Models\DiscussionPost;
use App\Models\LessonQuestionAnswer;
use App\Models\ModerationQueueItem;
use App\Models\PaymentAuditLog;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Community\ModerationService;
use App\Services\Community\QuestionAnswerService;
use App\Services\Community\ReputationService;
use App\Services\Payments\PaymentAuditLogger;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\Operations\AbstractOperationalRecordRequest;
use Modules\Admin\Http\Requests\Operations\UpdateOperationalRecordRequest;
use Modules\Admin\Registry\AdminResourceRegistry;

final class OperationalRecordActionService
{
    public function __construct(
        private readonly AdminResourceRegistry $registry,
        private readonly PaddleClient $paddle,
        private readonly PaymentAuditLogger $paymentAudit,
        private readonly AuditLogger $audit,
        private readonly ModerationService $moderation,
        private readonly QuestionAnswerService $questionAnswers,
        private readonly ReputationService $reputation,
    ) {}

    public function find(string $type, int $id): Model
    {
        return match ($type) {
            'course-purchase' => CoursePurchase::query()->with(['user', 'course', 'order'])->findOrFail($id),
            'entitlement' => PaymentEntitlement::query()->with(['user', 'teamAccount', 'entitlementable', 'order', 'subscription'])->findOrFail($id),
            'webhook-event' => PaymentWebhookEvent::query()->findOrFail($id),
            'payment-order' => PaymentOrder::query()->with(['user', 'items.course', 'items.product'])->findOrFail($id),
            'reconciliation' => PaymentReconciliationRecord::query()->findOrFail($id),
            'question' => CourseQuestion::query()->with(['user', 'course', 'lesson', 'acceptedAnswer.user'])->findOrFail($id),
            'answer' => LessonQuestionAnswer::query()->with(['user', 'question.course', 'question.lesson', 'acceptedBy'])->findOrFail($id),
            'discussion-post' => DiscussionPost::query()->with(['user', 'thread.forum'])->findOrFail($id),
            'group-member' => CommunityGroupMember::query()->with(['user', 'group.course'])->findOrFail($id),
            'reputation-score' => CommunityReputationScore::query()->with('user')->findOrFail($id),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    public function payload(string $type, Model $record): array
    {
        return [
            'record' => $this->recordSummary($type, $record),
            'details' => $this->details($type, $record),
            'evidence' => $this->evidence($type, $record),
            'fields' => $this->fields($type),
            'values' => $this->values($type, $record),
            'history' => $this->history($type, $record),
            'urls' => [
                'index' => $this->indexUrl($type),
                'update' => route('admin.operational-records.update', ['type' => $type, 'id' => $record->getKey()], false),
            ],
        ];
    }

    public function update(UpdateOperationalRecordRequest $request, string $type, Model $record): Model
    {
        $data = $request->validated();

        if ($type === 'payment-order') {
            return $this->refundOrder($request, $record, (string) $data['reason']);
        }

        return DB::transaction(function () use ($request, $type, $record, $data): Model {
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $locked->load($this->relations($type));
            $before = $locked->toArray();
            $action = (string) $data['action'];
            $reason = (string) $data['reason'];

            match ($type) {
                'course-purchase' => $this->changePurchaseAccess($locked, $action),
                'entitlement' => $this->changeEntitlementAccess($locked, $action),
                'webhook-event' => $this->retryWebhook($locked),
                'reconciliation' => $this->reconcile($locked, $reason),
                'question', 'answer', 'discussion-post' => $this->moderateContent($request, $type, $locked, $action, $reason),
                'group-member' => $this->changeGroupMember($locked, $action),
                'reputation-score' => $this->adjustReputation($request, $locked, (int) $data['points'], $reason),
                default => abort(404),
            };

            $locked->refresh();

            if ($this->isPaymentType($type)) {
                $this->paymentAudit->log(
                    'admin.payment.'.$action,
                    $locked,
                    $this->paymentUser($locked),
                    $before,
                    $locked->toArray(),
                    ['reason' => $reason],
                    $request,
                );
            } elseif ($type !== 'reputation-score') {
                $this->audit->log(
                    $request,
                    'admin.community.'.$action,
                    $locked,
                    $before,
                    $locked->toArray(),
                    ['reason' => $reason],
                );
            }

            return $locked;
        });
    }

    private function changePurchaseAccess(Model $record, string $action): void
    {
        abort_unless($record instanceof CoursePurchase, 500);
        $status = $action === 'restore' ? CoursePurchaseStatus::Active : CoursePurchaseStatus::Revoked;
        $record->forceFill(['status' => $status])->save();

        PaymentEntitlement::query()
            ->where('user_id', $record->user_id)
            ->where('entitlementable_type', $record->course->getMorphClass())
            ->where('entitlementable_id', $record->course_id)
            ->update([
                'status' => $action === 'restore' ? PaymentEntitlementStatus::Active->value : PaymentEntitlementStatus::Revoked->value,
                'updated_at' => now(),
            ]);
    }

    private function changeEntitlementAccess(Model $record, string $action): void
    {
        abort_unless($record instanceof PaymentEntitlement, 500);
        $record->forceFill([
            'status' => $action === 'restore' ? PaymentEntitlementStatus::Active : PaymentEntitlementStatus::Revoked,
        ])->save();

        if ($record->user_id && $record->entitlementable instanceof Course) {
            CoursePurchase::query()
                ->where('user_id', $record->user_id)
                ->where('course_id', $record->entitlementable_id)
                ->update([
                    'status' => $action === 'restore' ? CoursePurchaseStatus::Active->value : CoursePurchaseStatus::Revoked->value,
                    'updated_at' => now(),
                ]);
        }
    }

    private function retryWebhook(Model $record): void
    {
        abort_unless($record instanceof PaymentWebhookEvent, 500);

        if (in_array($record->status, ['queued', 'processing'], true)) {
            throw ValidationException::withMessages(['action' => 'This webhook is already queued or processing.']);
        }

        $record->forceFill([
            'status' => 'queued',
            'queued_at' => now(),
            'processed_at' => null,
            'last_error' => null,
        ])->save();

        ProcessPaddleWebhookEventJob::dispatch($record->id)->afterCommit();
    }

    private function reconcile(Model $record, string $reason): void
    {
        abort_unless($record instanceof PaymentReconciliationRecord, 500);

        if (blank($record->event_id)) {
            throw ValidationException::withMessages(['action' => 'This record has no source webhook event to reconcile.']);
        }

        $record->forceFill(['status' => 'pending', 'notes' => $reason])->save();
        ReconcilePaymentRecordJob::dispatch($record->id)->afterCommit();
    }

    private function moderateContent(UpdateOperationalRecordRequest $request, string $type, Model $record, string $action, string $reason): void
    {
        $moderator = $request->user();
        abort_unless($moderator instanceof User, 403);

        if ($type === 'answer' && $action === 'accept') {
            abort_unless($record instanceof LessonQuestionAnswer, 500);
            $this->questionAnswers->accept($record->question, $record, $moderator);
            $this->recordModerationHistory($record, CommunityContentStatus::Approved, $moderator, $reason);

            return;
        }

        if ($action === 'approve') {
            $this->moderation->approve($record, $moderator, $reason);
            $status = CommunityContentStatus::Approved;
        } elseif ($action === 'reject') {
            $this->moderation->reject($record, $moderator, $reason);
            $status = CommunityContentStatus::Rejected;
        } else {
            $status = $action === 'spam' ? CommunityContentStatus::Spam : CommunityContentStatus::Hidden;
            $record->forceFill([
                'status' => $record instanceof CourseQuestion ? PublishStatus::Rejected : $status,
            ])->save();
        }

        $this->recordModerationHistory($record, $status, $moderator, $reason);
    }

    private function recordModerationHistory(Model $record, CommunityContentStatus $status, User $moderator, string $reason): void
    {
        ModerationQueueItem::query()->create([
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'assigned_to' => $moderator->id,
            'status' => $status,
            'reason' => 'admin_manual_action',
            'reviewed_at' => now(),
            'resolution_note' => $reason,
        ]);
    }

    private function changeGroupMember(Model $record, string $action): void
    {
        abort_unless($record instanceof CommunityGroupMember, 500);
        $record->forceFill(['status' => $action === 'activate' ? 'active' : 'removed'])->save();
        $record->group->forceFill([
            'members_count' => $record->group->members()->where('status', 'active')->count(),
        ])->save();
    }

    private function adjustReputation(UpdateOperationalRecordRequest $request, Model $record, int $points, string $reason): void
    {
        abort_unless($record instanceof CommunityReputationScore, 500);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $before = $record->toArray();

        $this->reputation->record($record->user, $actor, ReputationEventType::AdminAdjustment, $points, $record, $reason);
        $record->refresh();
        $this->audit->log($request, 'admin.community.adjust_reputation', $record, $before, $record->toArray(), [
            'reason' => $reason,
            'points' => $points,
        ]);
    }

    private function refundOrder(UpdateOperationalRecordRequest $request, Model $record, string $reason): Model
    {
        abort_unless($record instanceof PaymentOrder, 500);

        return Cache::lock('payment-refund:'.$record->getKey(), 30)->block(5, function () use ($request, $record, $reason): Model {
            $order = PaymentOrder::query()->with('user')->findOrFail($record->getKey());

            if (! in_array($order->status, [PaymentOrderStatus::Paid, PaymentOrderStatus::Completed], true)) {
                throw ValidationException::withMessages(['action' => 'Only paid or completed orders can be refunded.']);
            }

            if (blank($order->paddle_transaction_id)) {
                throw ValidationException::withMessages(['action' => 'This order has no Paddle transaction ID.']);
            }

            $alreadyRequested = PaymentAuditLog::query()
                ->where('auditable_type', $order->getMorphClass())
                ->where('auditable_id', $order->getKey())
                ->where('action', 'payment.order.refund_requested')
                ->exists();

            if ($alreadyRequested) {
                throw ValidationException::withMessages(['action' => 'A refund has already been requested for this order.']);
            }

            $before = $order->toArray();
            $response = $this->paddle->refundTransaction($order, $reason);
            $status = (string) data_get($response, 'data.status', 'pending_approval');

            DB::transaction(function () use ($request, $order, $reason, $response, $status, $before): void {
                PaymentReconciliationRecord::query()->create([
                    'provider' => 'paddle',
                    'event_id' => data_get($response, 'data.id'),
                    'record_type' => 'refund',
                    'status' => $status,
                    'paddle_transaction_id' => $order->paddle_transaction_id,
                    'paddle_subscription_id' => $order->paddle_subscription_id,
                    'paddle_customer_id' => $order->paddle_customer_id,
                    'payload' => data_get($response, 'data', $response),
                    'reconciled_at' => $status === 'approved' ? now() : null,
                    'notes' => $reason,
                ]);

                if ($status === 'approved') {
                    $order->forceFill(['status' => PaymentOrderStatus::Refunded])->save();
                    CoursePurchase::query()->where('payment_order_id', $order->id)->update([
                        'status' => CoursePurchaseStatus::Refunded->value,
                        'updated_at' => now(),
                    ]);
                    PaymentEntitlement::query()->where('payment_order_id', $order->id)->update([
                        'status' => PaymentEntitlementStatus::Revoked->value,
                        'updated_at' => now(),
                    ]);
                }

                $this->paymentAudit->log(
                    'payment.order.refund_requested',
                    $order,
                    $order->user,
                    $before,
                    $order->fresh()?->toArray(),
                    ['reason' => $reason, 'paddle_adjustment_id' => data_get($response, 'data.id'), 'status' => $status],
                    $request,
                );
            });

            return $order->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function recordSummary(string $type, Model $record): array
    {
        return [
            'id' => $record->getKey(),
            'type' => $type,
            'eyebrow' => str($type)->replace('-', ' ')->headline()->toString(),
            'title' => $this->title($record),
            'subtitle' => $this->subtitle($record),
            'status' => $this->status($record),
        ];
    }

    /** @return array<int, array{label: string, value: string|null}> */
    private function details(string $type, Model $record): array
    {
        return match (true) {
            $record instanceof CoursePurchase => [$this->detail('Student', $record->user?->email), $this->detail('Course', $record->course?->title), $this->detail('Order', $record->order?->paddle_transaction_id), $this->detail('Purchased', $this->dateTime($record->purchased_at))],
            $record instanceof PaymentEntitlement => [$this->detail('Recipient', $record->user?->email ?? $record->teamAccount?->name), $this->detail('Type', $this->enumValue($record->type)), $this->detail('Order', $record->order?->paddle_transaction_id), $this->detail('Ends', $this->dateTime($record->ends_at))],
            $record instanceof PaymentWebhookEvent => [$this->detail('Event ID', $record->event_id), $this->detail('Event Type', $record->event_type), $this->detail('Attempts', (string) $record->attempts), $this->detail('Processed', $this->dateTime($record->processed_at))],
            $record instanceof PaymentOrder => [$this->detail('Customer', $record->user?->email), $this->detail('Transaction', $record->paddle_transaction_id), $this->detail('Total', strtoupper($record->currency).' '.number_format($record->total / 100, 2)), $this->detail('Purchased', $this->dateTime($record->purchased_at))],
            $record instanceof PaymentReconciliationRecord => [$this->detail('Event ID', $record->event_id), $this->detail('Type', $record->record_type), $this->detail('Transaction', $record->paddle_transaction_id), $this->detail('Reconciled', $this->dateTime($record->reconciled_at))],
            $record instanceof CourseQuestion => [$this->detail('Student', $record->user?->email), $this->detail('Course', $record->course?->title), $this->detail('Lesson', $record->lesson?->title), $this->detail('Accepted answer', $record->acceptedAnswer?->user?->name)],
            $record instanceof LessonQuestionAnswer => [$this->detail('Author', $record->user?->email), $this->detail('Question', $record->question?->title), $this->detail('Course', $record->question?->course?->title), $this->detail('Accepted by', $record->acceptedBy?->name)],
            $record instanceof DiscussionPost => [$this->detail('Author', $record->user?->email), $this->detail('Thread', $record->thread?->title), $this->detail('Forum', $record->thread?->forum?->title), $this->detail('Upvotes', (string) $record->upvotes_count)],
            $record instanceof CommunityGroupMember => [$this->detail('Member', $record->user?->email), $this->detail('Group', $record->group?->name), $this->detail('Course', $record->group?->course?->title), $this->detail('Role', $record->role)],
            $record instanceof CommunityReputationScore => [$this->detail('Member', $record->user?->email), $this->detail('Points', (string) $record->points), $this->detail('Level', $record->level), $this->detail('Updated', $this->dateTime($record->updated_at))],
            default => [],
        };
    }

    /** @return array<int, array{label: string, value: string, format: string}> */
    private function evidence(string $type, Model $record): array
    {
        $items = match (true) {
            $record instanceof PaymentWebhookEvent => [['Webhook payload', $this->json($record->payload), 'json'], ['Last error', $record->last_error ?: 'No processing error recorded.', 'text']],
            $record instanceof PaymentOrder => [['Paddle payload', $this->json($record->payload), 'json']],
            $record instanceof PaymentReconciliationRecord => [['Reconciliation payload', $this->json($record->payload), 'json'], ['Notes', $record->notes ?: 'No notes recorded.', 'text']],
            $record instanceof PaymentEntitlement => [['Entitlement metadata', $this->json($record->metadata), 'json']],
            $record instanceof CourseQuestion => [['Question', $record->body, 'text']],
            $record instanceof LessonQuestionAnswer => [['Answer', $record->body, 'text']],
            $record instanceof DiscussionPost => [['Post', $record->body, 'text']],
            default => [],
        };

        return array_map(fn (array $item): array => ['label' => $item[0], 'value' => $item[1], 'format' => $item[2]], $items);
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(string $type): array
    {
        $actions = match ($type) {
            'course-purchase', 'entitlement' => [['label' => 'Revoke access', 'value' => 'revoke'], ['label' => 'Restore access', 'value' => 'restore']],
            'webhook-event' => [['label' => 'Retry webhook', 'value' => 'retry']],
            'payment-order' => [['label' => 'Request full refund', 'value' => 'refund']],
            'reconciliation' => [['label' => 'Reconcile from source event', 'value' => 'reconcile']],
            'question', 'discussion-post' => $this->moderationOptions(),
            'answer' => [...$this->moderationOptions(), ['label' => 'Accept answer', 'value' => 'accept']],
            'group-member' => [['label' => 'Activate membership', 'value' => 'activate'], ['label' => 'Remove membership', 'value' => 'remove']],
            'reputation-score' => [['label' => 'Append points adjustment', 'value' => 'adjust']],
            default => [],
        };

        $fields = [$this->field('action', 'Authorized action', 'select', $actions, true)];
        if ($type === 'reputation-score') {
            $fields[] = $this->field('points', 'Points adjustment', 'number', required: true, min: -1000, max: 1000);
        }
        $fields[] = $this->field('reason', 'Reason', 'textarea', required: true);

        return $fields;
    }

    /** @return array<string, string|int|null> */
    private function values(string $type, Model $record): array
    {
        $action = match ($type) {
            'course-purchase', 'entitlement' => $this->status($record) === 'active' ? 'revoke' : 'restore',
            'webhook-event' => 'retry',
            'payment-order' => 'refund',
            'reconciliation' => 'reconcile',
            'question', 'answer', 'discussion-post' => 'approve',
            'group-member' => $this->status($record) === 'active' ? 'remove' : 'activate',
            'reputation-score' => 'adjust',
            default => '',
        };

        return ['action' => $action, 'points' => $type === 'reputation-score' ? 0 : null, 'reason' => ''];
    }

    /** @return array<int, array<string, mixed>> */
    private function history(string $type, Model $record): array
    {
        $logs = $this->isPaymentType($type)
            ? PaymentAuditLog::query()->with('actor:id,name')->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->getKey())->latest()->limit(20)->get()
            : AuditLog::query()->with('actor:id,name')->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->getKey())->latest()->limit(20)->get();

        return $logs->map(function (PaymentAuditLog|AuditLog $log): array {
            $metadata = $log->getAttribute('metadata');
            $before = $log->getAttribute('before');
            $after = $log->getAttribute('after');

            return [
                'id' => $log->getKey(),
                'action' => str($log->action)->afterLast('.')->replace('_', ' ')->headline()->toString(),
                'actor' => $log->actor?->name,
                'reason' => is_array($metadata) ? ($metadata['reason'] ?? null) : null,
                'from_status' => is_array($before) ? ($before['status'] ?? null) : null,
                'to_status' => is_array($after) ? ($after['status'] ?? null) : null,
                'created_at' => $this->dateTime($log->created_at),
            ];
        })->all();
    }

    /** @return list<string> */
    private function relations(string $type): array
    {
        return match ($type) {
            'course-purchase' => ['user', 'course', 'order'],
            'entitlement' => ['user', 'teamAccount', 'entitlementable', 'order', 'subscription'],
            'payment-order' => ['user', 'items.course', 'items.product'],
            'question' => ['user', 'course', 'lesson', 'acceptedAnswer.user'],
            'answer' => ['user', 'question.course', 'question.lesson', 'acceptedBy'],
            'discussion-post' => ['user', 'thread.forum'],
            'group-member' => ['user', 'group.course'],
            'reputation-score' => ['user'],
            default => [],
        };
    }

    private function indexUrl(string $type): string
    {
        $resource = AbstractOperationalRecordRequest::RESOURCE_BY_TYPE[$type] ?? abort(404);

        return '/admin/'.$this->registry->get($resource)->path;
    }

    private function isPaymentType(string $type): bool
    {
        return in_array($type, ['course-purchase', 'entitlement', 'webhook-event', 'payment-order', 'reconciliation'], true);
    }

    private function paymentUser(Model $record): ?User
    {
        return match (true) {
            $record instanceof CoursePurchase, $record instanceof PaymentEntitlement, $record instanceof PaymentOrder => $record->user,
            default => null,
        };
    }

    private function title(Model $record): string
    {
        return match (true) {
            $record instanceof CoursePurchase => $record->course?->title ?? 'Course purchase #'.$record->id,
            $record instanceof PaymentEntitlement => 'Entitlement #'.$record->id,
            $record instanceof PaymentWebhookEvent => $record->event_type,
            $record instanceof PaymentOrder => $record->paddle_transaction_id ?: 'Payment order #'.$record->id,
            $record instanceof PaymentReconciliationRecord => ucfirst($record->record_type).' reconciliation #'.$record->id,
            $record instanceof CourseQuestion => $record->title,
            $record instanceof LessonQuestionAnswer => 'Answer to '.$record->question?->title,
            $record instanceof DiscussionPost => 'Post in '.$record->thread?->title,
            $record instanceof CommunityGroupMember => $record->user?->name.' in '.$record->group?->name,
            $record instanceof CommunityReputationScore => $record->user?->name.' reputation',
            default => class_basename($record).' #'.$record->getKey(),
        };
    }

    private function subtitle(Model $record): string
    {
        return match (true) {
            $record instanceof CoursePurchase, $record instanceof PaymentEntitlement, $record instanceof PaymentOrder => $record->user?->email ?? 'No customer attached',
            $record instanceof PaymentWebhookEvent => $record->event_id,
            $record instanceof PaymentReconciliationRecord => $record->event_id ?? 'No source event attached',
            $record instanceof CourseQuestion, $record instanceof LessonQuestionAnswer, $record instanceof DiscussionPost, $record instanceof CommunityGroupMember, $record instanceof CommunityReputationScore => $record->user?->email ?? 'No user attached',
            default => '',
        };
    }

    private function status(Model $record): string
    {
        if ($record instanceof CommunityReputationScore) {
            return $record->level;
        }

        return $this->enumValue($record->getAttribute('status'));
    }

    /** @return array<int, array{label: string, value: string}> */
    private function moderationOptions(): array
    {
        return [
            ['label' => 'Approve', 'value' => 'approve'],
            ['label' => 'Reject', 'value' => 'reject'],
            ['label' => 'Mark as spam', 'value' => 'spam'],
            ['label' => 'Hide', 'value' => 'hide'],
        ];
    }

    /** @return array{label: string, value: string|null} */
    private function detail(string $label, ?string $value): array
    {
        return compact('label', 'value');
    }

    /** @param array<int, array{label: string, value: string}> $options @return array<string, mixed> */
    private function field(string $name, string $label, string $type, array $options = [], bool $required = false, ?int $min = null, ?int $max = null): array
    {
        return compact('name', 'label', 'type', 'options', 'required', 'min', 'max');
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateTimeString() : null;
    }

    private function json(mixed $value): string
    {
        return $value === null || $value === []
            ? 'No data recorded.'
            : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
