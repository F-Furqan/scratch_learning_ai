<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use App\Enums\PublishStatus;
use Illuminate\Validation\Rule;

final class UpdatePublishingRequest extends AbstractCourseBuilderRequest
{
    /** @var list<string> */
    public const ALLOWED_STATUSES = [
        PublishStatus::Draft->value,
        PublishStatus::Pending->value,
        PublishStatus::Submitted->value,
        PublishStatus::Approved->value,
        PublishStatus::Published->value,
        PublishStatus::ChangesRequested->value,
        PublishStatus::Rejected->value,
        PublishStatus::Archived->value,
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(self::ALLOWED_STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:4000'],
            'rejection_reason' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
