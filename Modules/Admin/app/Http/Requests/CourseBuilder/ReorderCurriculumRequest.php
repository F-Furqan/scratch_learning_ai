<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use Illuminate\Validation\Rule;

final class ReorderCurriculumRequest extends AbstractCourseBuilderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['sections', 'lessons'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
