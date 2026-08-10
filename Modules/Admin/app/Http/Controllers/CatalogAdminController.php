<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Admin\AdminOperationController;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Actions\CreateAdminResourceAction;
use Modules\Admin\Actions\ReorderCatalogResourcesAction;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Http\Requests\IndexAdminResourceRequest;
use Modules\Admin\Http\Requests\QuickStoreCourseCategoryRequest;
use Modules\Admin\Http\Requests\ReorderCatalogResourceRequest;
use Modules\Admin\Services\CourseCatalogAdminService;

final class CatalogAdminController extends AdminOperationController
{
    public function reorder(
        ReorderCatalogResourceRequest $request,
        ReorderCatalogResourcesAction $action,
    ): JsonResponse {
        $resource = (string) $request->route('resource');
        $action->execute($request, $resource, $request->validated('items'));

        return response()->json(['message' => 'Order updated.']);
    }

    public function quickStoreCategory(
        QuickStoreCourseCategoryRequest $request,
        CourseCatalogAdminService $catalog,
        CreateAdminResourceAction $action,
    ): JsonResponse {
        $record = $action->execute(
            $request,
            'course_categories',
            fn () => $catalog->persist($request, 'course_categories'),
        );

        abort_unless($record instanceof CourseCategory, 500);

        return response()->json([
            'data' => [
                'label' => $record->name,
                'value' => $record->id,
            ],
        ], 201);
    }

    public function subcategories(
        IndexAdminResourceRequest $request,
        CourseCategory $category,
        CourseCatalogAdminService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->subcategoriesFor($category)]);
    }

    public function lessons(
        IndexAdminResourceRequest $request,
        Course $course,
        CourseCatalogAdminService $catalog,
    ): JsonResponse {
        return response()->json(['data' => $catalog->lessonsFor($course)]);
    }

    protected function domain(): AdminDomain
    {
        return AdminDomain::Catalog;
    }
}
