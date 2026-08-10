<?php

namespace Modules\Admin\Http\Requests;

final class ReorderCatalogResourceRequest extends AbstractAdminResourceRequest
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
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
