<?php

namespace Modules\Admin\Http\Requests;

final class IndexAdminResourceRequest extends AbstractAdminResourceRequest
{
    protected function ability(): string
    {
        return 'view';
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:128'],
            'role' => ['nullable', 'string', 'max:128'],
            'group' => ['nullable', 'string', 'max:128'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
