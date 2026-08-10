<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\MenuBuilder\ReorderMenuItemsRequest;
use Modules\Admin\Http\Requests\MenuBuilder\ShowMenuBuilderRequest;
use Modules\Admin\Services\AdminMenuBuilderService;

final class AdminMenuBuilderController extends Controller
{
    public function show(ShowMenuBuilderRequest $request, Menu $menu, AdminMenuBuilderService $builder): Response
    {
        return Inertia::render('admin/editorial/MenuBuilder', $builder->payload($menu));
    }

    public function reorder(ReorderMenuItemsRequest $request, Menu $menu, AdminMenuBuilderService $builder): JsonResponse
    {
        $builder->reorder($request, $menu, $request->validated('items'));

        return response()->json(['message' => 'Menu hierarchy saved.']);
    }
}
