<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Admin\AdminOperationController;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Actions\ReorderEditorialResourcesAction;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Http\Requests\ReorderCatalogResourceRequest;

final class EditorialAdminController extends AdminOperationController
{
    public function reorder(
        ReorderCatalogResourceRequest $request,
        ReorderEditorialResourcesAction $action,
    ): JsonResponse {
        $resource = (string) $request->route('resource');
        $action->execute($request, $resource, $request->validated('items'));

        return response()->json(['message' => 'Order updated.']);
    }

    protected function domain(): AdminDomain
    {
        return AdminDomain::Editorial;
    }
}
