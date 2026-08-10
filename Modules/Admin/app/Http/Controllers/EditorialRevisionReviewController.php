<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EditorialRevision;
use App\Models\ReviewerComment;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\EditorialReview\StoreReviewerCommentRequest;
use Modules\Admin\Services\EditorialRevisionComparisonService;

final class EditorialRevisionReviewController extends Controller
{
    public function show(Request $request, EditorialRevision $revision, EditorialRevisionComparisonService $comparison): Response
    {
        abort_unless($request->user()?->canAny(['manage_blogs', 'manage_courses', 'manage_cms']), 403);

        return Inertia::render('admin/editorial/RevisionReview', $comparison->payload($revision));
    }

    public function storeComment(
        StoreReviewerCommentRequest $request,
        EditorialRevision $revision,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $comment = $revision->comments()->create([
            ...$request->validated(),
            'reviewer_id' => $request->user()->id,
            'is_resolved' => false,
        ]);
        $auditLogger->log($request, 'admin.editorial_revision.comment_created', $comment, null, $comment->toArray());

        return back()->with('success', 'Reviewer comment added.');
    }

    public function resolveComment(
        Request $request,
        EditorialRevision $revision,
        ReviewerComment $comment,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($request->user()?->canAny(['manage_blogs', 'manage_courses', 'manage_cms']), 403);
        abort_unless((int) $comment->editorial_revision_id === (int) $revision->id, 404);
        $before = $comment->toArray();
        $comment->forceFill([
            'is_resolved' => ! $comment->is_resolved,
            'resolved_by' => $comment->is_resolved ? null : $request->user()->id,
            'resolved_at' => $comment->is_resolved ? null : now(),
        ])->save();
        $auditLogger->log($request, 'admin.editorial_revision.comment_resolution_toggled', $comment, $before, $comment->fresh()?->toArray());

        return back()->with('success', $comment->is_resolved ? 'Comment resolved.' : 'Comment reopened.');
    }
}
