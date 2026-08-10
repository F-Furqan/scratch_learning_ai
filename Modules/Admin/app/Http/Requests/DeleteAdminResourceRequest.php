<?php

namespace Modules\Admin\Http\Requests;

final class DeleteAdminResourceRequest extends AbstractAdminResourceRequest
{
    protected function ability(): string
    {
        return 'manage';
    }

    public function rules(): array
    {
        return [];
    }
}
