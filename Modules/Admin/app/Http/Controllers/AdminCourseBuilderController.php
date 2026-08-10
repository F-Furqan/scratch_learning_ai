<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\CourseBuilder\DeleteCourseBuilderItemRequest;
use Modules\Admin\Http\Requests\CourseBuilder\MutateItemRequest;
use Modules\Admin\Http\Requests\CourseBuilder\ReorderCurriculumRequest;
use Modules\Admin\Http\Requests\CourseBuilder\ShowCourseBuilderRequest;
use Modules\Admin\Http\Requests\CourseBuilder\UpdateCourseRequest;
use Modules\Admin\Http\Requests\CourseBuilder\UpdateOwnershipRequest;
use Modules\Admin\Http\Requests\CourseBuilder\UpdateProductRequest;
use Modules\Admin\Http\Requests\CourseBuilder\UpdatePublishingRequest;
use Modules\Admin\Services\AdminCourseBuilderService;

final class AdminCourseBuilderController extends Controller
{
    public function show(ShowCourseBuilderRequest $request, Course $course, AdminCourseBuilderService $builder): Response
    {
        return Inertia::render('admin/courses/Builder', $builder->payload($course));
    }

    public function update(UpdateCourseRequest $request, Course $course, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->updateCourse($request, $course, $request->validated());

        return back()->with('success', 'Course information saved.');
    }

    public function ownership(UpdateOwnershipRequest $request, Course $course, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->updateOwnership($request, $course, $request->validated());

        return back()->with('success', 'Ownership confirmation saved.');
    }

    public function publishing(UpdatePublishingRequest $request, Course $course, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->updatePublishing($request, $course, $request->validated());

        return back()->with('success', 'Publishing decision saved.');
    }

    public function product(UpdateProductRequest $request, Course $course, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->updateProduct($request, $course, $request->validated());

        return back()->with('success', 'Pricing and access settings saved.');
    }

    public function storeItem(MutateItemRequest $request, Course $course, string $kind, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->storeItem($request, $course, $kind, $request->validated());

        return back()->with('success', 'Course builder item added.');
    }

    public function updateItem(MutateItemRequest $request, Course $course, string $kind, int $id, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->updateItem($request, $course, $kind, $id, $request->validated());

        return back()->with('success', 'Course builder item saved.');
    }

    public function destroyItem(DeleteCourseBuilderItemRequest $request, Course $course, string $kind, int $id, AdminCourseBuilderService $builder): RedirectResponse
    {
        $builder->deleteItem($request, $course, $kind, $id);

        return back()->with('success', 'Course builder item deleted.');
    }

    public function reorder(ReorderCurriculumRequest $request, Course $course, AdminCourseBuilderService $builder): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');
        $builder->reorder($request, $course, (string) $request->validated('type'), $ids);

        return back()->with('success', 'Curriculum order saved.');
    }
}
