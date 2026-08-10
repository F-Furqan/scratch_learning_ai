<?php

namespace Modules\Admin\Http\Requests\MenuBuilder;

use Illuminate\Foundation\Http\FormRequest;

final class ShowMenuBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_cms') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
