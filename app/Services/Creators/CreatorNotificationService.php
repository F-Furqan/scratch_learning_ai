<?php

namespace App\Services\Creators;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use App\Notifications\CreatorWorkflowNotification;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

class CreatorNotificationService
{
    public function blogSubmitted(BlogPost $post): void
    {
        $this->notify($post->author, [
            'event' => 'blog_submitted',
            'severity' => 'info',
            'title' => 'Blog submitted',
            'message' => sprintf('"%s" is waiting for admin review.', $post->title),
            ...$this->contentPayload('blog', $post, route('creator.blogs.edit', $post, false)),
        ]);
    }

    public function blogApproved(BlogPost $post, ?string $note = null): void
    {
        $this->notify($post->author, [
            'event' => 'blog_approved',
            'severity' => 'success',
            'title' => 'Blog approved',
            'message' => sprintf('"%s" was approved by the admin team.', $post->title),
            'note' => $note,
            ...$this->contentPayload('blog', $post, route('creator.blogs.edit', $post, false)),
        ]);
    }

    public function blogRejected(BlogPost $post, ?string $note = null): void
    {
        $this->notify($post->author, [
            'event' => 'blog_rejected',
            'severity' => 'danger',
            'title' => 'Blog rejected',
            'message' => sprintf('"%s" was rejected after review.', $post->title),
            'note' => $note,
            ...$this->contentPayload('blog', $post, route('creator.blogs.edit', $post, false)),
        ]);
    }

    public function blogChangesRequested(BlogPost $post, ?string $note = null): void
    {
        $this->notify($post->author, [
            'event' => 'blog_changes_requested',
            'severity' => 'warning',
            'title' => 'Blog changes requested',
            'message' => sprintf('Admin requested changes for "%s".', $post->title),
            'note' => $note,
            ...$this->contentPayload('blog', $post, route('creator.blogs.edit', $post, false)),
        ]);
    }

    public function courseSubmitted(Course $course): void
    {
        $this->notify($course->creator, [
            'event' => 'course_submitted',
            'severity' => 'info',
            'title' => 'Course submitted',
            'message' => sprintf('"%s" is waiting for admin review.', $course->title),
            ...$this->contentPayload('course', $course, route('creator.courses.edit', $course, false)),
        ]);
    }

    public function courseApproved(Course $course, ?string $note = null): void
    {
        $this->notify($course->creator, [
            'event' => 'course_approved',
            'severity' => 'success',
            'title' => 'Course approved',
            'message' => sprintf('"%s" was approved and published.', $course->title),
            'note' => $note,
            ...$this->contentPayload('course', $course, route('creator.courses.edit', $course, false)),
        ]);
    }

    public function courseRejected(Course $course, ?string $note = null): void
    {
        $this->notify($course->creator, [
            'event' => 'course_rejected',
            'severity' => 'danger',
            'title' => 'Course rejected',
            'message' => sprintf('"%s" was rejected after review.', $course->title),
            'note' => $note,
            ...$this->contentPayload('course', $course, route('creator.courses.edit', $course, false)),
        ]);
    }

    public function courseChangesRequested(Course $course, ?string $note = null): void
    {
        $this->notify($course->creator, [
            'event' => 'course_changes_requested',
            'severity' => 'warning',
            'title' => 'Course changes requested',
            'message' => sprintf('Admin requested changes for "%s".', $course->title),
            'note' => $note,
            ...$this->contentPayload('course', $course, route('creator.courses.edit', $course, false)),
        ]);
    }

    public function revisionApproved(EditorialRevision $revision, ?string $note = null): void
    {
        $this->revisionDecision($revision, 'approved', 'success', 'Revision approved', $note);
    }

    public function revisionRejected(EditorialRevision $revision, ?string $note = null): void
    {
        $this->revisionDecision($revision, 'rejected', 'danger', 'Revision rejected', $note);
    }

    public function revisionChangesRequested(EditorialRevision $revision, ?string $note = null): void
    {
        $this->revisionDecision($revision, 'changes_requested', 'warning', 'Revision changes requested', $note);
    }

    public function deletionApproved(CreatorContentDeletionRequest $request, ?Model $content = null, ?string $note = null): void
    {
        $this->deletionDecision($request, $content, 'delete_approved', 'success', 'Delete request approved', 'Your delete request was approved.', $note);
    }

    public function deletionRejected(CreatorContentDeletionRequest $request, ?Model $content = null, ?string $note = null): void
    {
        $this->deletionDecision($request, $content, 'delete_rejected', 'warning', 'Delete request rejected', 'Your delete request was rejected.', $note);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notify(?User $creator, array $payload): void
    {
        if (! $creator instanceof User) {
            return;
        }

        $creator->notify(new CreatorWorkflowNotification([
            'note' => null,
            ...$payload,
        ]));
    }

    private function revisionDecision(EditorialRevision $revision, string $decision, string $severity, string $title, ?string $note): void
    {
        $content = $revision->editorialable;
        $contentType = $content instanceof Course ? 'course' : 'blog';
        $event = $contentType.'_revision_'.$decision;
        $actionUrl = null;
        $contentTitle = $revision->title;

        if ($content instanceof BlogPost) {
            $actionUrl = route('creator.blogs.edit', $content, false);
            $contentTitle = $content->title;
        }

        if ($content instanceof Course) {
            $actionUrl = route('creator.courses.edit', $content, false);
            $contentTitle = $content->title;
        }

        $this->notify($revision->author, [
            'event' => $event,
            'severity' => $severity,
            'title' => $title,
            'message' => sprintf('Your revision for "%s" was marked %s.', $contentTitle, str_replace('_', ' ', $decision)),
            'note' => $note,
            'content_type' => $contentType,
            'content_id' => $content instanceof Model ? $content->getKey() : null,
            'content_title' => $contentTitle,
            'status' => $this->statusValue($revision->getAttribute('status')),
            'action_url' => $actionUrl,
        ]);
    }

    private function deletionDecision(
        CreatorContentDeletionRequest $request,
        ?Model $content,
        string $event,
        string $severity,
        string $title,
        string $message,
        ?string $note,
    ): void {
        $content ??= $request->content;
        $contentType = $content instanceof Course ? 'course' : 'blog';

        $this->notify($request->requester, [
            'event' => $contentType.'_'.$event,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'note' => $note,
            'content_type' => $contentType,
            'content_id' => $content instanceof Model ? $content->getKey() : $request->content_id,
            'content_title' => $content instanceof BlogPost || $content instanceof Course ? $content->title : null,
            'status' => $this->statusValue($request->getAttribute('status')),
            'action_url' => route('creator.dashboard', absolute: false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentPayload(string $type, BlogPost|Course $content, string $actionUrl): array
    {
        return [
            'content_type' => $type,
            'content_id' => $content->id,
            'content_title' => $content->title,
            'status' => $this->statusValue($content->getAttribute('status')),
            'action_url' => $actionUrl,
        ];
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }
}
