<?php

namespace App\Services\Community;

use App\Enums\CommunityContentStatus;
use App\Enums\PublishStatus;
use App\Models\ModerationQueueItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ModerationService
{
    public function __construct(
        private readonly SpamGuardService $spamGuard,
    ) {}

    public function moderate(Model $subject, string $body, ?User $actor = null, string $reason = 'community_content'): ModerationQueueItem
    {
        $inspection = $this->spamGuard->inspect($body);
        $status = $inspection['is_spam'] ? CommunityContentStatus::Spam : CommunityContentStatus::Pending;

        $this->applyPendingStatus($subject, $status);

        return ModerationQueueItem::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reporter_id' => $actor?->id,
            'status' => $status,
            'reason' => $reason,
            'spam_score' => min(255, $inspection['score']),
            'matched_terms' => $inspection['matched_terms'],
        ]);
    }

    public function approve(Model $subject, ?User $moderator = null, ?string $note = null): void
    {
        $this->setSubjectStatus($subject, CommunityContentStatus::Approved, PublishStatus::Published);
        $this->markQueueItems($subject, CommunityContentStatus::Approved, $moderator, $note);
    }

    public function reject(Model $subject, ?User $moderator = null, ?string $note = null): void
    {
        $this->setSubjectStatus($subject, CommunityContentStatus::Rejected, PublishStatus::Rejected);
        $this->markQueueItems($subject, CommunityContentStatus::Rejected, $moderator, $note);
    }

    private function applyPendingStatus(Model $subject, CommunityContentStatus $status): void
    {
        if (! $subject->isFillable('status')) {
            return;
        }

        $this->setSubjectStatus(
            $subject,
            $status,
            $status === CommunityContentStatus::Spam ? PublishStatus::Rejected : PublishStatus::Pending,
        );
    }

    private function setSubjectStatus(Model $subject, CommunityContentStatus $communityStatus, PublishStatus $publishStatus): void
    {
        if (! $subject->isFillable('status')) {
            return;
        }

        $casts = $subject->getCasts();
        $status = ($casts['status'] ?? null) === PublishStatus::class ? $publishStatus : $communityStatus;

        $subject->forceFill(['status' => $status])->save();
    }

    private function markQueueItems(Model $subject, CommunityContentStatus $status, ?User $moderator, ?string $note): void
    {
        ModerationQueueItem::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('status', [CommunityContentStatus::Pending->value, CommunityContentStatus::Spam->value])
            ->update([
                'status' => $status->value,
                'assigned_to' => $moderator?->id,
                'reviewed_at' => now(),
                'resolution_note' => $note,
                'updated_at' => now(),
            ]);
    }
}
