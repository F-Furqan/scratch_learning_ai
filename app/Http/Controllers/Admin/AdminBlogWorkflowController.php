<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Services\Content\BlogWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminBlogWorkflowController extends Controller
{
    public function approve(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->approveSubmission($request, $post, $this->note($request));

        return back()->with('status', 'blog-approved');
    }

    public function requestChanges(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->requestSubmissionChanges($request, $post, $this->note($request));

        return back()->with('status', 'blog-changes-requested');
    }

    public function reject(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->rejectSubmission($request, $post, $this->note($request));

        return back()->with('status', 'blog-rejected');
    }

    public function publish(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->publishApproved($request, $post, $this->note($request));

        return back()->with('status', 'blog-published');
    }

    public function approveRevision(Request $request, EditorialRevision $revision, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->applyRevision($request, $revision, $this->note($request));

        return back()->with('status', 'blog-revision-approved');
    }

    public function requestRevisionChanges(Request $request, EditorialRevision $revision, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->requestRevisionChanges($request, $revision, $this->note($request));

        return back()->with('status', 'blog-revision-changes-requested');
    }

    public function rejectRevision(Request $request, EditorialRevision $revision, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->rejectRevision($request, $revision, $this->note($request));

        return back()->with('status', 'blog-revision-rejected');
    }

    public function approveDeletion(Request $request, CreatorContentDeletionRequest $deletionRequest, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->approveDeletionRequest($request, $deletionRequest, $this->note($request));

        return back()->with('status', 'blog-delete-approved');
    }

    public function rejectDeletion(Request $request, CreatorContentDeletionRequest $deletionRequest, BlogWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $workflow->rejectDeletionRequest($request, $deletionRequest, $this->note($request));

        return back()->with('status', 'blog-delete-rejected');
    }

    private function authorizeWorkflow(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageBlogs->value), 403);
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
