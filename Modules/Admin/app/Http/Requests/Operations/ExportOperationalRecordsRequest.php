<?php

namespace Modules\Admin\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;
use Modules\Admin\Services\CommerceOperationsAdminService;
use Modules\Admin\Services\CommunityOperationsAdminService;
use Modules\Admin\Services\OperationsCenterAdminService;

final class ExportOperationalRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $resource = (string) $this->route('resource');

        if (! $user instanceof User
            || ! $user->can('admin.records.export')
            || (! in_array($resource, CommerceOperationsAdminService::RESOURCES, true)
                && ! in_array($resource, CommunityOperationsAdminService::RESOURCES, true)
                && ! in_array($resource, OperationsCenterAdminService::RESOURCES, true))) {
            return false;
        }

        return app(AdminResourcePolicy::class)->view(
            $user,
            app(AdminResourceRegistry::class)->get($resource),
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }
}
