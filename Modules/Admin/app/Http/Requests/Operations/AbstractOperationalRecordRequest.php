<?php

namespace Modules\Admin\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;

abstract class AbstractOperationalRecordRequest extends FormRequest
{
    /** @var array<string, string> */
    public const RESOURCE_BY_TYPE = [
        'course-purchase' => 'course_purchases',
        'entitlement' => 'payment_entitlements',
        'webhook-event' => 'payment_webhook_events',
        'payment-order' => 'payment_orders',
        'reconciliation' => 'payment_reconciliation',
        'question' => 'course_questions',
        'answer' => 'lesson_question_answers',
        'discussion-post' => 'discussion_posts',
        'group-member' => 'community_group_members',
        'reputation-score' => 'community_reputation_scores',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $resource = self::RESOURCE_BY_TYPE[$this->recordType()] ?? null;

        if (! $user instanceof User || ! is_string($resource)) {
            return false;
        }

        $definition = app(AdminResourceRegistry::class)->get($resource);
        $policy = app(AdminResourcePolicy::class);

        if ($this->isMethod('get')) {
            return $policy->view($user, $definition);
        }

        return $policy->manage($user, $definition)
            && $user->can($this->sensitivePermission());
    }

    public function recordType(): string
    {
        return (string) $this->route('type');
    }

    public function resourceKey(): string
    {
        return self::RESOURCE_BY_TYPE[$this->recordType()] ?? '';
    }

    private function sensitivePermission(): string
    {
        return match ($this->recordType()) {
            'course-purchase', 'entitlement' => 'admin.payments.revoke_access',
            'webhook-event' => 'admin.payments.retry_webhook',
            'payment-order' => 'admin.payments.refund',
            'reconciliation' => 'admin.payments.reconcile',
            'answer' => $this->input('action') === 'accept'
                ? 'admin.community.accept_answers'
                : 'admin.community.moderate',
            'question', 'discussion-post' => 'admin.community.moderate',
            'group-member' => 'admin.community.manage_members',
            'reputation-score' => 'admin.community.adjust_reputation',
            default => 'admin.operations.manage',
        };
    }
}
