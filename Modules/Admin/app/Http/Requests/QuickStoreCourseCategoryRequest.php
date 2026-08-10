<?php

namespace Modules\Admin\Http\Requests;

final class QuickStoreCourseCategoryRequest extends AbstractAdminResourceRequest
{
    protected function ability(): string
    {
        return 'manage';
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
