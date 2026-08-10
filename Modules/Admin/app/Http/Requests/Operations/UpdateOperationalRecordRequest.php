<?php

namespace Modules\Admin\Http\Requests\Operations;

use Illuminate\Validation\Rule;

final class UpdateOperationalRecordRequest extends AbstractOperationalRecordRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $actions = match ($this->recordType()) {
            'course-purchase', 'entitlement' => ['revoke', 'restore'],
            'webhook-event' => ['retry'],
            'payment-order' => ['refund'],
            'reconciliation' => ['reconcile'],
            'question', 'discussion-post' => ['approve', 'reject', 'spam', 'hide'],
            'answer' => ['approve', 'reject', 'spam', 'hide', 'accept'],
            'group-member' => ['activate', 'remove'],
            'reputation-score' => ['adjust'],
            default => [],
        };

        return [
            'action' => ['required', 'string', Rule::in($actions)],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'points' => [
                Rule::requiredIf($this->recordType() === 'reputation-score'),
                'nullable',
                'integer',
                'not_in:0',
                'between:-1000,1000',
            ],
        ];
    }
}
