<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use Illuminate\Validation\Rule;

final class UpdateOwnershipRequest extends AbstractCourseBuilderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ownership_video_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'ownership_video_url' => ['nullable', 'url', 'max:2048'],
            'ownership_statement' => ['required', 'string', 'max:2000'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (blank($this->input('ownership_video_media_id')) && blank($this->input('ownership_video_url'))) {
                    $validator->errors()->add('ownership_video_url', 'Upload or link an ownership confirmation video.');
                }
            },
        ];
    }
}
