<?php

namespace Modules\Admin\Http\Requests\EditorialReview;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReviewerCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['manage_blogs', 'manage_courses', 'manage_cms']) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'field_path' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
