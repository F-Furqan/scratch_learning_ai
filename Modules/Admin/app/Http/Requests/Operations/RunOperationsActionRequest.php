<?php

namespace Modules\Admin\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class RunOperationsActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = match ((string) $this->route('operation')) {
            'backup' => 'admin.operations.backup',
            'health' => 'admin.operations.run_health',
            'monitor' => 'admin.operations.run_monitor',
            default => null,
        };

        return $user instanceof User && is_string($permission) && $user->can($permission);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'verify' => ['sometimes', 'boolean'],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
