<?php

namespace Modules\Admin\Http\Requests;

final class UpdateAdminResourceRequest extends AbstractAdminResourceRequest
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
