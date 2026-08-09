<?php

namespace App\Services\Community;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityReportStatus;
use App\Models\ContentReport;
use App\Models\ModerationQueueItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContentReportService
{
    /**
     * @param  array{reason: string, details?: string|null}  $payload
     */
    public function report(Model $reportable, User $reporter, array $payload): ContentReport
    {
        return DB::transaction(function () use ($reportable, $reporter, $payload): ContentReport {
            $report = ContentReport::query()->create([
                'reporter_id' => $reporter->id,
                'reportable_type' => $reportable->getMorphClass(),
                'reportable_id' => $reportable->getKey(),
                'status' => CommunityReportStatus::Open,
                'reason' => $payload['reason'],
                'details' => $payload['details'] ?? null,
            ]);

            ModerationQueueItem::query()->create([
                'subject_type' => $reportable->getMorphClass(),
                'subject_id' => $reportable->getKey(),
                'reporter_id' => $reporter->id,
                'status' => CommunityContentStatus::Pending,
                'reason' => 'reported: '.$payload['reason'],
                'spam_score' => 0,
                'matched_terms' => [],
            ]);

            return $report;
        });
    }
}
