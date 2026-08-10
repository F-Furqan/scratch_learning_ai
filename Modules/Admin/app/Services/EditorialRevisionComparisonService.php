<?php

namespace Modules\Admin\Services;

use App\Enums\EditorialRevisionStatus;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\EditorialRevision;
use App\Models\ReviewerComment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EditorialRevisionComparisonService
{
    /** @return array<string, mixed> */
    public function payload(EditorialRevision $revision): array
    {
        $revision->load(['editorialable', 'author', 'reviewer', 'comments.reviewer', 'comments.resolver', 'approvalHistories.actor']);
        $content = $revision->editorialable;
        $proposed = $this->proposedPayload($revision);
        $current = $this->currentPayload($content, $proposed);

        return [
            'revision' => [
                'id' => $revision->id,
                'title' => $revision->title,
                'summary' => $revision->summary,
                'status' => $this->enumValue($revision->status),
                'content_type' => $content ? class_basename($content) : 'Unlinked',
                'content_title' => $content?->getAttribute('title'),
                'author' => $revision->author?->name,
                'reviewer' => $revision->reviewer?->name,
                'submitted_at' => $this->dateTimeString($revision->getAttribute('submitted_at')),
            ],
            'fields' => $this->comparisonRows($current, $proposed),
            'comments' => $revision->comments->map(fn (ReviewerComment $comment): array => [
                'id' => $comment->id,
                'field_path' => $comment->field_path,
                'body' => $comment->body,
                'reviewer' => $comment->reviewer?->name,
                'is_resolved' => $comment->is_resolved,
                'resolved_by' => $comment->resolver?->name,
                'created_at' => $comment->created_at?->toDateTimeString(),
                'resolve_url' => route('admin.editorial.revisions.comments.resolve', [$revision, $comment], false),
            ])->values()->all(),
            'history' => $revision->approvalHistories->map(fn ($history): array => [
                'decision' => $history->decision,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'note' => $history->note,
                'actor' => $history->actor?->name,
                'created_at' => $history->created_at?->toDateTimeString(),
            ])->values()->all(),
            'actions' => $this->workflowActions($revision, $content),
            'urls' => [
                'index' => route('admin.editorial.revisions.index', absolute: false),
                'comments' => route('admin.editorial.revisions.comments.store', $revision, false),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function proposedPayload(EditorialRevision $revision): array
    {
        $rawPayload = $revision->getAttribute('payload');
        $payload = is_array($rawPayload) ? $rawPayload : [];
        unset($payload['review_note']);

        if (is_array($payload['course'] ?? null)) {
            $course = $payload['course'];
            if (is_array($payload['operations'] ?? null)) {
                $course['operations'] = $payload['operations'];
            }

            return $course;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @return array<string, mixed>
     */
    private function currentPayload(?Model $content, array $proposed): array
    {
        if (! $content) {
            return [];
        }

        $attributes = $content->toArray();
        $current = [];
        foreach (array_keys($proposed) as $key) {
            $current[$key] = $key === 'operations' ? [] : ($attributes[$key] ?? null);
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @return array<int, array<string, mixed>>
     */
    private function comparisonRows(array $current, array $proposed): array
    {
        $rows = [];
        foreach ($proposed as $key => $value) {
            $currentValue = $current[$key] ?? null;
            $rows[] = [
                'path' => (string) $key,
                'label' => Str::headline((string) $key),
                'current' => $this->displayValue($currentValue),
                'proposed' => $this->displayValue($value),
                'changed' => $this->normalize($currentValue) !== $this->normalize($value),
                'is_rich_text' => in_array($key, ['content', 'description', 'body', 'answer'], true),
            ];
        }

        return $rows;
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) ($value ?? '');
    }

    private function normalize(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }

    /** @return array<int, array{label: string, url: string, tone: string}> */
    private function workflowActions(EditorialRevision $revision, ?Model $content): array
    {
        $status = $this->enumValue($revision->status);
        if (! in_array($status, [EditorialRevisionStatus::Submitted->value, EditorialRevisionStatus::ChangesRequested->value], true)) {
            return [];
        }

        $prefix = match (true) {
            $content instanceof BlogPost => 'admin.blog-workflow.revisions.',
            $content instanceof Course => 'admin.course-workflow.revisions.',
            default => null,
        };
        if (! $prefix) {
            return [];
        }

        $actions = [
            ['label' => 'Apply Revision', 'url' => route($prefix.'approve', $revision, false), 'tone' => 'success'],
        ];
        if ($status === EditorialRevisionStatus::Submitted->value) {
            $actions[] = ['label' => 'Request Changes', 'url' => route($prefix.'changes-requested', $revision, false), 'tone' => 'warning'];
        }
        $actions[] = ['label' => 'Reject', 'url' => route($prefix.'reject', $revision, false), 'tone' => 'danger'];

        return $actions;
    }

    private function enumValue(mixed $value): string
    {
        return (string) ($value instanceof \BackedEnum ? $value->value : $value);
    }

    private function dateTimeString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface
            ? $value->toDateTimeString()
            : (is_string($value) ? $value : null);
    }
}
