<?php

namespace Modules\Admin\Http\Requests\MenuBuilder;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_cms') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:250'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
