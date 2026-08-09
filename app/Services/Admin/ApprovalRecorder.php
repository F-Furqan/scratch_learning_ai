<?php

namespace App\Services\Admin;

use App\Models\ApprovalHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ApprovalRecorder
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(Request $request, Model $subject, string $decision, mixed $fromStatus, mixed $toStatus, ?string $note = null, ?array $metadata = null): ApprovalHistory
    {
        $metadata = $this->withReviewChecklist($request, $metadata);

        return ApprovalHistory::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'actor_id' => $request->user()?->id,
            'decision' => $decision,
            'from_status' => $this->statusValue($fromStatus),
            'to_status' => $this->statusValue($toStatus),
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function withReviewChecklist(Request $request, ?array $metadata): ?array
    {
        $checklist = $request->input('review_checklist');

        if (! is_array($checklist)) {
            return $metadata;
        }

        $normalized = [];

        foreach ($checklist as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $normalized[(string) $key] = is_bool($value)
                ? $value
                : in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
        }

        if ($normalized === []) {
            return $metadata;
        }

        return [
            ...($metadata ?? []),
            'review_checklist' => $normalized,
        ];
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }
}
