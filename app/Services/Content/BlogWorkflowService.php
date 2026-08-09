<?php

namespace App\Services\Content;

use App\Enums\CreatorContentDeletionStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Models\BlogPost;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Creators\CreatorNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BlogWorkflowService
{
    /**
     * @var list<string>
     */
    private const BLOG_PAYLOAD_FIELDS = [
        'blog_category_id',
        'title',
        'excerpt',
        'content',
        'seo_title',
        'seo_description',
    ];

    public function __construct(
        private readonly ApprovalRecorder $approvals,
        private readonly CreatorNotificationService $notifications,
    ) {}

    public function submitForReview(Request $request, BlogPost $post): BlogPost
    {
        $from = $post->getAttribute('status');

        $post->forceFill([
            'status' => PublishStatus::Submitted,
            'published_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->approvals->record(
            $request,
            $post,
            'submitted',
            $from,
            PublishStatus::Submitted,
            'Creator submitted blog for approval.',
        );

        $this->notifications->blogSubmitted($post);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitPublishedRevision(Request $request, BlogPost $post, User $creator, array $payload): EditorialRevision
    {
        $revision = $this->activeRevisionFor($post);
        $from = $revision?->getAttribute('status');
        $revisionPayload = Arr::only($payload, self::BLOG_PAYLOAD_FIELDS);

        $attributes = [
            'author_id' => $creator->id,
            'reviewer_id' => null,
            'title' => (string) ($revisionPayload['title'] ?? $post->title),
            'summary' => $revisionPayload['excerpt'] ?? null,
            'payload' => $revisionPayload,
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'published_at' => null,
        ];

        if ($revision instanceof EditorialRevision) {
            $revision->fill($attributes)->save();
        } else {
            $revision = $post->editorialRevisions()->create($attributes);
        }

        $this->approvals->record(
            $request,
            $revision,
            'revision_submitted',
            $from,
            EditorialRevisionStatus::Submitted,
            'Creator submitted a revision for this published blog.',
            ['blog_post_id' => $post->id],
        );

        return $revision;
    }

    public function approveSubmission(Request $request, BlogPost $post, ?string $note = null): BlogPost
    {
        $this->ensureBlogStatus($post, [PublishStatus::Submitted, PublishStatus::ChangesRequested], 'Only submitted blogs can be approved.');

        $post = $this->transitionBlog($request, $post, PublishStatus::Approved, 'approved', $note);

        $this->notifications->blogApproved($post, $note);

        return $post;
    }

    public function requestSubmissionChanges(Request $request, BlogPost $post, ?string $note = null): BlogPost
    {
        $this->ensureBlogStatus($post, [PublishStatus::Submitted, PublishStatus::Approved], 'Only submitted or approved blogs can receive change requests.');

        $post = $this->transitionBlog($request, $post, PublishStatus::ChangesRequested, 'changes_requested', $note);

        $this->notifications->blogChangesRequested($post, $note);

        return $post;
    }

    public function rejectSubmission(Request $request, BlogPost $post, ?string $note = null): BlogPost
    {
        $this->ensureBlogStatus($post, [PublishStatus::Submitted, PublishStatus::Approved, PublishStatus::ChangesRequested], 'Only reviewed blogs can be rejected.');

        $post = $this->transitionBlog($request, $post, PublishStatus::Rejected, 'rejected', $note);

        $this->notifications->blogRejected($post, $note);

        return $post;
    }

    public function publishApproved(Request $request, BlogPost $post, ?string $note = null): BlogPost
    {
        $this->ensureBlogStatus($post, [PublishStatus::Approved], 'Approve this blog before publishing it.');

        $from = $post->getAttribute('status');
        $post->forceFill([
            'status' => PublishStatus::Published,
            'published_at' => $post->published_at ?: now(),
            'admin_notes' => $note ?: $post->admin_notes,
            'rejection_reason' => null,
        ])->save();

        $this->approvals->record($request, $post, 'published', $from, PublishStatus::Published, $note);

        return $post;
    }

    public function applyRevision(Request $request, EditorialRevision $revision, ?string $note = null): BlogPost
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted, EditorialRevisionStatus::ChangesRequested], 'Only submitted revisions can be approved.');

        $post = $this->blogPostFor($revision);
        $revisionPayload = $revision->getAttribute('payload');
        $payload = is_array($revisionPayload) ? Arr::only($revisionPayload, self::BLOG_PAYLOAD_FIELDS) : [];

        /** @var BlogPost $post */
        $post = DB::transaction(function () use ($request, $revision, $post, $payload, $note): BlogPost {
            $revisionFrom = $revision->getAttribute('status');
            $postFrom = $post->getAttribute('status');

            $post->fill($payload);
            $post->forceFill([
                'status' => PublishStatus::Published,
                'published_at' => $post->published_at ?: now(),
                'admin_notes' => $note ?: $post->admin_notes,
                'rejection_reason' => null,
            ])->save();

            $revision->forceFill([
                'reviewer_id' => $request->user()?->id,
                'status' => EditorialRevisionStatus::Approved,
                'reviewed_at' => now(),
            ])->save();

            $this->approvals->record(
                $request,
                $revision,
                'revision_approved',
                $revisionFrom,
                EditorialRevisionStatus::Approved,
                $note,
                ['blog_post_id' => $post->id],
            );

            $this->approvals->record(
                $request,
                $post,
                'revision_applied',
                $postFrom,
                PublishStatus::Published,
                $note,
                ['revision_id' => $revision->id],
            );

            return $post;
        });

        $this->notifications->revisionApproved($revision, $note);

        return $post;
    }

    public function requestRevisionChanges(Request $request, EditorialRevision $revision, ?string $note = null): EditorialRevision
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted], 'Only submitted revisions can receive change requests.');

        $revision = $this->transitionRevision($request, $revision, EditorialRevisionStatus::ChangesRequested, 'revision_changes_requested', $note);

        $this->notifications->revisionChangesRequested($revision, $note);

        return $revision;
    }

    public function rejectRevision(Request $request, EditorialRevision $revision, ?string $note = null): EditorialRevision
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted, EditorialRevisionStatus::ChangesRequested], 'Only active revisions can be rejected.');

        $revision = $this->transitionRevision($request, $revision, EditorialRevisionStatus::Rejected, 'revision_rejected', $note);

        $this->notifications->revisionRejected($revision, $note);

        return $revision;
    }

    public function requestDeletion(Request $request, BlogPost $post, User $creator, ?string $reason = null): CreatorContentDeletionRequest
    {
        $deletionRequest = CreatorContentDeletionRequest::query()->firstOrCreate(
            [
                'requester_id' => $creator->id,
                'content_type' => $post->getMorphClass(),
                'content_id' => $post->id,
                'status' => CreatorContentDeletionStatus::Pending,
            ],
            [
                'reason' => $reason ?: 'Creator requested deletion.',
            ],
        );

        if ($post->getAttribute('status') !== PublishStatus::DeleteRequested) {
            $this->transitionBlog(
                $request,
                $post,
                PublishStatus::DeleteRequested,
                'delete_requested',
                $reason ?: 'Creator requested deletion.',
            );
        }

        return $deletionRequest;
    }

    public function approveDeletionRequest(Request $request, CreatorContentDeletionRequest $deletionRequest, ?string $note = null): CreatorContentDeletionRequest
    {
        return DB::transaction(function () use ($request, $deletionRequest, $note): CreatorContentDeletionRequest {
            $this->ensureDeletionRequestPending($deletionRequest);

            $content = $deletionRequest->content;
            $from = $deletionRequest->getAttribute('status');

            $deletionRequest->forceFill([
                'status' => CreatorContentDeletionStatus::Approved,
                'decided_by' => $request->user()?->id,
                'decided_at' => now(),
                'admin_note' => $note,
            ])->save();

            if ($content instanceof BlogPost) {
                $this->transitionBlog($request, $content, PublishStatus::Trashed, 'trashed', $note);
                $content->delete();
            }

            $this->approvals->record(
                $request,
                $deletionRequest,
                'delete_approved',
                $from,
                CreatorContentDeletionStatus::Approved,
                $note,
                $this->deletionRequestMetadata($deletionRequest),
            );

            $this->notifications->deletionApproved($deletionRequest, $content, $note);

            return $deletionRequest;
        });
    }

    public function rejectDeletionRequest(Request $request, CreatorContentDeletionRequest $deletionRequest, ?string $note = null): CreatorContentDeletionRequest
    {
        return DB::transaction(function () use ($request, $deletionRequest, $note): CreatorContentDeletionRequest {
            $this->ensureDeletionRequestPending($deletionRequest);

            $content = $deletionRequest->content;
            $from = $deletionRequest->getAttribute('status');

            $deletionRequest->forceFill([
                'status' => CreatorContentDeletionStatus::Rejected,
                'decided_by' => $request->user()?->id,
                'decided_at' => now(),
                'admin_note' => $note,
            ])->save();

            if ($content instanceof BlogPost && $this->statusValue($content->getAttribute('status')) === PublishStatus::DeleteRequested->value) {
                $this->transitionBlog($request, $content, PublishStatus::Published, 'delete_rejected', $note);
            }

            $this->approvals->record(
                $request,
                $deletionRequest,
                'delete_rejected',
                $from,
                CreatorContentDeletionStatus::Rejected,
                $note,
                $this->deletionRequestMetadata($deletionRequest),
            );

            $this->notifications->deletionRejected($deletionRequest, $content, $note);

            return $deletionRequest;
        });
    }

    private function activeRevisionFor(BlogPost $post): ?EditorialRevision
    {
        return $post->editorialRevisions()
            ->whereIn('status', [
                EditorialRevisionStatus::Submitted->value,
                EditorialRevisionStatus::ChangesRequested->value,
            ])
            ->latest()
            ->first();
    }

    /**
     * @param  list<PublishStatus>  $allowed
     */
    private function ensureBlogStatus(BlogPost $post, array $allowed, string $message): void
    {
        $status = $post->getAttribute('status');

        foreach ($allowed as $allowedStatus) {
            if ($status === $allowedStatus || $status === $allowedStatus->value) {
                return;
            }
        }

        abort(422, $message);
    }

    /**
     * @param  list<EditorialRevisionStatus>  $allowed
     */
    private function ensureRevisionStatus(EditorialRevision $revision, array $allowed, string $message): void
    {
        $status = $revision->getAttribute('status');

        foreach ($allowed as $allowedStatus) {
            if ($status === $allowedStatus || $status === $allowedStatus->value) {
                return;
            }
        }

        abort(422, $message);
    }

    private function ensureDeletionRequestPending(CreatorContentDeletionRequest $deletionRequest): void
    {
        abort_unless(
            $this->statusValue($deletionRequest->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value,
            422,
            'Only pending deletion requests can be decided.',
        );
    }

    private function transitionBlog(Request $request, BlogPost $post, PublishStatus $to, string $decision, ?string $note = null): BlogPost
    {
        $from = $post->getAttribute('status');

        $post->forceFill([
            'status' => $to,
            'published_at' => $to === PublishStatus::Published ? ($post->published_at ?: now()) : null,
            'admin_notes' => $to === PublishStatus::ChangesRequested || filled($note) ? ($note ?: $post->admin_notes) : $post->admin_notes,
            'rejection_reason' => in_array($to, [PublishStatus::ChangesRequested, PublishStatus::Rejected], true) ? $note : null,
        ])->save();

        $this->approvals->record($request, $post, $decision, $from, $to, $note);

        return $post;
    }

    private function transitionRevision(Request $request, EditorialRevision $revision, EditorialRevisionStatus $to, string $decision, ?string $note = null): EditorialRevision
    {
        $from = $revision->getAttribute('status');
        $revisionPayload = $revision->getAttribute('payload');
        $payload = is_array($revisionPayload) ? $revisionPayload : [];

        if (filled($note)) {
            $payload['review_note'] = $note;
        }

        $revision->forceFill([
            'reviewer_id' => $request->user()?->id,
            'status' => $to,
            'payload' => $payload,
            'reviewed_at' => now(),
        ])->save();

        $this->approvals->record(
            $request,
            $revision,
            $decision,
            $from,
            $to,
            $note,
            ['blog_post_id' => $this->blogPostFor($revision)->id],
        );

        return $revision;
    }

    private function blogPostFor(EditorialRevision $revision): BlogPost
    {
        $content = $revision->editorialable;

        abort_unless($content instanceof BlogPost, 422, 'This revision is not linked to a blog post.');

        return $content;
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function deletionRequestMetadata(CreatorContentDeletionRequest $deletionRequest): array
    {
        return [
            'content_type' => $deletionRequest->content_type,
            'content_id' => $deletionRequest->content_id,
            'requester_id' => $deletionRequest->requester_id,
        ];
    }
}
