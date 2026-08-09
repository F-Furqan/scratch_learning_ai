<?php

namespace App\Services\Creators;

use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Enums\ScheduledPublicationStatus;
use App\Models\EditorialRevision;
use App\Models\ReviewerComment;
use App\Models\ScheduledPublication;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditorialWorkflowService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRevision(Model $content, User $author, array $payload, ?string $summary = null): EditorialRevision
    {
        return EditorialRevision::query()->create([
            'editorialable_type' => $content->getMorphClass(),
            'editorialable_id' => $content->getKey(),
            'author_id' => $author->id,
            'title' => (string) ($payload['title'] ?? $content->getAttribute('title') ?? 'Editorial revision'),
            'summary' => $summary,
            'payload' => $payload,
            'status' => EditorialRevisionStatus::Draft,
        ]);
    }

    public function submitForReview(EditorialRevision $revision): EditorialRevision
    {
        $revision->forceFill([
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        return $revision;
    }

    public function approve(EditorialRevision $revision, User $reviewer): EditorialRevision
    {
        $revision->forceFill([
            'reviewer_id' => $reviewer->id,
            'status' => EditorialRevisionStatus::Approved,
            'reviewed_at' => now(),
        ])->save();

        return $revision;
    }

    public function requestChanges(EditorialRevision $revision, User $reviewer, string $comment, ?string $fieldPath = null): ReviewerComment
    {
        $revision->forceFill([
            'reviewer_id' => $reviewer->id,
            'status' => EditorialRevisionStatus::ChangesRequested,
            'reviewed_at' => now(),
        ])->save();

        return $this->comment($revision, $reviewer, $comment, $fieldPath);
    }

    public function comment(EditorialRevision $revision, User $reviewer, string $body, ?string $fieldPath = null): ReviewerComment
    {
        return ReviewerComment::query()->create([
            'editorial_revision_id' => $revision->id,
            'reviewer_id' => $reviewer->id,
            'field_path' => $fieldPath,
            'body' => $body,
        ]);
    }

    public function schedule(Model $content, User $creator, CarbonInterface $publishAt, ?EditorialRevision $revision = null, ?User $approver = null): ScheduledPublication
    {
        if ($revision) {
            $revision->forceFill([
                'scheduled_at' => $publishAt,
                'status' => EditorialRevisionStatus::Approved,
            ])->save();
        }

        return ScheduledPublication::query()->create([
            'publishable_type' => $content->getMorphClass(),
            'publishable_id' => $content->getKey(),
            'editorial_revision_id' => $revision?->id,
            'created_by' => $creator->id,
            'approved_by' => $approver?->id,
            'publish_at' => $publishAt,
            'timezone' => config('app.timezone', 'UTC'),
            'status' => ScheduledPublicationStatus::Scheduled,
        ]);
    }

    public function publishDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $count = 0;

        ScheduledPublication::query()
            ->where('status', ScheduledPublicationStatus::Scheduled->value)
            ->where('publish_at', '<=', $now)
            ->with(['revision', 'publishable'])
            ->get()
            ->each(function (ScheduledPublication $publication) use (&$count, $now): void {
                DB::transaction(function () use ($publication, $now, &$count): void {
                    $content = $publication->publishable;

                    if (! $content instanceof Model) {
                        $publication->forceFill([
                            'status' => ScheduledPublicationStatus::Failed,
                            'failure_reason' => 'Publishable content no longer exists.',
                        ])->save();

                        return;
                    }

                    $payload = $this->revisionPayload($publication->revision);

                    if ($payload !== []) {
                        $content->fill($payload);
                    }

                    if ($content->isFillable('status') || array_key_exists('status', $content->getAttributes())) {
                        $content->setAttribute('status', PublishStatus::Published);
                    }

                    if ($content->isFillable('published_at') || array_key_exists('published_at', $content->getAttributes())) {
                        $content->setAttribute('published_at', $now);
                    }

                    $content->save();

                    $publication->revision?->forceFill([
                        'status' => EditorialRevisionStatus::Published,
                        'published_at' => $now,
                    ])->save();

                    $publication->forceFill([
                        'status' => ScheduledPublicationStatus::Published,
                        'published_at' => $now,
                    ])->save();

                    $count++;
                });
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionPayload(?EditorialRevision $revision): array
    {
        if (! $revision) {
            return [];
        }

        $payload = $revision->getAttribute('payload');

        return is_array($payload) ? $payload : [];
    }
}
