<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Services\Content\CourseWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCourseWorkflowController extends Controller
{
    public function approve(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->approve($request, $course, $this->note($request));

        return back()->with('status', 'course-approved');
    }

    public function requestChanges(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->requestChanges($request, $course, $this->note($request));

        return back()->with('status', 'course-changes-requested');
    }

    public function reject(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->reject($request, $course, $this->note($request));

        return back()->with('status', 'course-rejected');
    }

    public function approveRevision(Request $request, EditorialRevision $revision, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->applyRevision($request, $revision, $this->note($request));

        return back()->with('status', 'course-revision-approved');
    }

    public function requestRevisionChanges(Request $request, EditorialRevision $revision, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->requestRevisionChanges($request, $revision, $this->note($request));

        return back()->with('status', 'course-revision-changes-requested');
    }

    public function rejectRevision(Request $request, EditorialRevision $revision, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->rejectRevision($request, $revision, $this->note($request));

        return back()->with('status', 'course-revision-rejected');
    }

    public function approveDeletion(Request $request, CreatorContentDeletionRequest $deletionRequest, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->approveDeletionRequest($request, $deletionRequest, $this->note($request));

        return back()->with('status', 'course-delete-approved');
    }

    public function rejectDeletion(Request $request, CreatorContentDeletionRequest $deletionRequest, CourseWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->rejectDeletionRequest($request, $deletionRequest, $this->note($request));

        return back()->with('status', 'course-delete-rejected');
    }

    private function authorizeWorkflow(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageCourses->value), 403);
    }

    private function note(Request $request): ?string
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'review_checklist' => ['nullable', 'array'],
            'review_checklist.*' => ['boolean'],
        ]);

        return $validated['note'] ?? null;
    }
}
