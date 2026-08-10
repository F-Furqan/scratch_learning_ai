<?php

namespace Modules\Admin\Services;

use App\Enums\DatabaseBackupStatus;
use App\Models\ApplicationLogEntry;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\FailedJob;
use App\Models\HealthCheckRun;
use App\Models\OperationalAlert;
use App\Models\PaymentWebhookEvent;
use App\Models\QueueJob;
use App\Models\SchedulerHeartbeat;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class OperationsCenterAdminService
{
    /** @var list<string> */
    public const RESOURCES = [
        'database_backups',
        'queue_jobs',
        'failed_jobs',
        'scheduler_heartbeats',
        'paddle_webhook_health',
        'application_logs',
        'audit_logs',
        'approval_histories',
        'health_checks',
        'operational_alerts',
    ];

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function readOnly(string $resource): bool
    {
        return $this->supports($resource);
    }

    public function exportable(string $resource): bool
    {
        return $this->supports($resource);
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'database_backups' => ['database', 'driver', 'status', 'verification', 'size', 'checksum', 'started_at', 'completed_at'],
            'queue_jobs' => ['job', 'queue', 'status', 'attempts', 'available_at', 'created_at'],
            'failed_jobs' => ['job', 'connection', 'queue', 'failure', 'failed_at'],
            'scheduler_heartbeats' => ['name', 'status', 'duration', 'started_at', 'completed_at'],
            'paddle_webhook_health' => ['event_id', 'event_type', 'status', 'attempts', 'queued_at', 'processed_at', 'last_error'],
            'application_logs' => ['level', 'channel', 'environment', 'message', 'context', 'occurred_at'],
            'audit_logs' => ['action', 'actor', 'record', 'ip_address', 'changes', 'created_at'],
            'approval_histories' => ['subject', 'decision', 'transition', 'reviewer', 'note', 'created_at'],
            'health_checks' => ['check', 'status', 'details', 'checked_at'],
            'operational_alerts' => ['title', 'source', 'severity', 'status', 'occurrences', 'last_detected_at', 'owner'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /** @return array<int, array<string, mixed>> */
    public function fields(string $resource): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];
        $statuses = match ($resource) {
            'database_backups' => $this->enumOptions(DatabaseBackupStatus::cases()),
            'scheduler_heartbeats' => $this->valueOptions(['running', 'healthy', 'warning', 'failed']),
            'paddle_webhook_health' => $this->valueOptions(['accepted', 'queued', 'processing', 'processed', 'failed']),
            'application_logs' => $this->valueOptions(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']),
            'health_checks' => $this->valueOptions(['healthy', 'unhealthy']),
            'operational_alerts' => $this->valueOptions(['open', 'acknowledged', 'resolved']),
            default => [],
        };

        if ($statuses !== []) {
            $filters[] = $this->field('status', $resource === 'application_logs' ? 'Level' : 'Status', 'select', $statuses);
        }

        $categories = $this->categoryOptions($resource);
        if ($categories !== []) {
            $filters[] = $this->field('category', $this->categoryLabel($resource), 'select', $categories);
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkActions(string $resource): array
    {
        return match ($resource) {
            'database_backups' => [$this->bulkAction('verify', 'Queue restore verification', needsNote: true)],
            'failed_jobs' => [
                $this->bulkAction('retry', 'Retry selected jobs', needsNote: true),
                $this->bulkAction('forget', 'Forget selected jobs', needsNote: true),
            ],
            'operational_alerts' => [
                $this->bulkAction('acknowledge', 'Acknowledge alerts', needsNote: true),
                $this->bulkAction('resolve', 'Resolve alerts', needsNote: true),
            ],
            default => [],
        };
    }

    /** @return array<string, int> */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'database_backups' => ['successful' => DatabaseBackup::query()->where('status', 'succeeded')->count(), 'failed' => DatabaseBackup::query()->where('status', 'failed')->count(), 'verified' => DatabaseBackup::query()->where('verification_status', 'verified')->count()],
            'queue_jobs' => ['pending' => QueueJob::query()->count(), 'reserved' => QueueJob::query()->whereNotNull('reserved_at')->count(), 'heartbeat_age_seconds' => $this->queueHeartbeatAge()],
            'failed_jobs' => ['failed' => FailedJob::query()->count(), 'queues' => FailedJob::query()->distinct()->count('queue')],
            'scheduler_heartbeats' => ['runs' => SchedulerHeartbeat::query()->count(), 'failed' => SchedulerHeartbeat::query()->where('status', 'failed')->count()],
            'paddle_webhook_health' => ['processed' => PaymentWebhookEvent::query()->where('provider', 'paddle')->where('status', 'processed')->count(), 'failed' => PaymentWebhookEvent::query()->where('provider', 'paddle')->where('status', 'failed')->count(), 'stale' => PaymentWebhookEvent::query()->where('provider', 'paddle')->whereIn('status', ['accepted', 'processing'])->where('queued_at', '<=', now()->subMinutes((int) config('operations.monitoring.paddle_stale_minutes', 10)))->count()],
            'application_logs' => ['events' => ApplicationLogEntry::query()->count(), 'errors' => ApplicationLogEntry::query()->whereIn('level', ['error', 'critical', 'alert', 'emergency'])->count()],
            'audit_logs' => ['events' => AuditLog::query()->count(), 'actors' => AuditLog::query()->whereNotNull('actor_id')->distinct()->count('actor_id')],
            'approval_histories' => ['decisions' => ApprovalHistory::query()->count(), 'reviewers' => ApprovalHistory::query()->whereNotNull('actor_id')->distinct()->count('actor_id')],
            'health_checks' => ['healthy' => HealthCheckRun::query()->where('status', 'healthy')->count(), 'unhealthy' => HealthCheckRun::query()->where('status', 'unhealthy')->count()],
            'operational_alerts' => ['open' => OperationalAlert::query()->where('status', 'open')->count(), 'acknowledged' => OperationalAlert::query()->where('status', 'acknowledged')->count(), 'resolved' => OperationalAlert::query()->where('status', 'resolved')->count()],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'database_backups' => $this->backupRow($record),
            'queue_jobs' => $this->queueRow($record),
            'failed_jobs' => $this->failedRow($record),
            'scheduler_heartbeats' => $this->schedulerRow($record),
            'paddle_webhook_health' => $this->webhookRow($record),
            'application_logs' => $this->logRow($record),
            'audit_logs' => $this->auditRow($record),
            'approval_histories' => $this->approvalRow($record),
            'health_checks' => $this->healthRow($record),
            'operational_alerts' => $this->alertRow($record),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function backupRow(Model $record): array
    {
        abort_unless($record instanceof DatabaseBackup, 500);

        return $this->baseRow($record) + [
            'database' => $record->database_name,
            'driver' => $record->driver,
            'status' => $this->enumValue($record->status),
            'verification' => $this->enumValue($record->verification_status),
            'size' => $record->bytes === null ? null : number_format($record->bytes).' bytes',
            'checksum' => $record->checksum === null ? null : substr($record->checksum, 0, 12).'...',
            'started_at' => $this->dateTime($record->started_at),
            'completed_at' => $this->dateTime($record->completed_at),
        ];
    }

    /** @return array<string, mixed> */
    private function queueRow(Model $record): array
    {
        abort_unless($record instanceof QueueJob, 500);

        return $this->baseRow($record) + [
            'job' => $this->jobName($record->payload),
            'queue' => $record->queue,
            'status' => $record->reserved_at === null ? 'pending' : 'reserved',
            'attempts' => $record->attempts,
            'available_at' => $this->timestamp($record->available_at),
            'created_at' => $this->timestamp($record->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function failedRow(Model $record): array
    {
        abort_unless($record instanceof FailedJob, 500);

        return $this->baseRow($record) + [
            'job' => $this->jobName($record->payload),
            'connection' => $record->connection,
            'queue' => $record->queue,
            'failure' => str($record->exception)->before("\n")->limit(180)->toString(),
            'failed_at' => $this->dateTime($record->failed_at),
        ];
    }

    /** @return array<string, mixed> */
    private function schedulerRow(Model $record): array
    {
        abort_unless($record instanceof SchedulerHeartbeat, 500);

        return $this->baseRow($record) + [
            'name' => $record->name,
            'status' => $record->status,
            'duration' => $record->duration_ms === null ? null : $record->duration_ms.' ms',
            'started_at' => $this->dateTime($record->started_at),
            'completed_at' => $this->dateTime($record->completed_at),
        ];
    }

    /** @return array<string, mixed> */
    private function webhookRow(Model $record): array
    {
        abort_unless($record instanceof PaymentWebhookEvent, 500);

        return $this->baseRow($record) + [
            'event_id' => $record->event_id,
            'event_type' => $record->event_type,
            'status' => $record->status,
            'attempts' => $record->attempts,
            'queued_at' => $this->dateTime($record->queued_at),
            'processed_at' => $this->dateTime($record->processed_at),
            'last_error' => $record->last_error,
            'workflow_actions' => [[
                'label' => 'Manage',
                'url' => route('admin.operational-records.show', ['type' => 'webhook-event', 'id' => $record->getKey()], false),
                'tone' => 'warning',
                'method' => 'get',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function logRow(Model $record): array
    {
        abort_unless($record instanceof ApplicationLogEntry, 500);

        return $this->baseRow($record) + [
            'level' => $record->level,
            'channel' => $record->channel,
            'environment' => $record->environment,
            'message' => $record->message,
            'context' => $this->jsonSummary($record->context),
            'occurred_at' => $this->dateTime($record->occurred_at),
        ];
    }

    /** @return array<string, mixed> */
    private function auditRow(Model $record): array
    {
        abort_unless($record instanceof AuditLog, 500);

        return $this->baseRow($record) + [
            'action' => $record->action,
            'actor' => $record->actor?->email ?? 'System',
            'record' => class_basename((string) $record->auditable_type).($record->auditable_id ? ' #'.$record->auditable_id : ''),
            'ip_address' => $record->ip_address,
            'changes' => $this->jsonSummary($record->metadata ?? $record->after),
            'created_at' => $this->dateTime($record->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function approvalRow(Model $record): array
    {
        abort_unless($record instanceof ApprovalHistory, 500);

        return $this->baseRow($record) + [
            'subject' => class_basename((string) $record->subject_type).' #'.$record->subject_id,
            'decision' => $record->decision,
            'transition' => ($record->from_status ?? 'none').' -> '.($record->to_status ?? 'none'),
            'reviewer' => $record->actor?->email ?? 'System',
            'note' => $record->note,
            'created_at' => $this->dateTime($record->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function healthRow(Model $record): array
    {
        abort_unless($record instanceof HealthCheckRun, 500);

        return $this->baseRow($record) + [
            'check' => $record->check_name,
            'status' => $record->status,
            'details' => $this->jsonSummary($record->metadata),
            'checked_at' => $this->dateTime($record->checked_at),
        ];
    }

    /** @return array<string, mixed> */
    private function alertRow(Model $record): array
    {
        abort_unless($record instanceof OperationalAlert, 500);

        return $this->baseRow($record) + [
            'title' => $record->title,
            'source' => $record->source,
            'severity' => $record->severity,
            'status' => $record->status,
            'occurrences' => $record->occurrence_count,
            'last_detected_at' => $this->dateTime($record->last_detected_at),
            'owner' => $record->resolvedBy?->email ?? $record->acknowledgedBy?->email,
        ];
    }

    /** @return array<string, mixed> */
    private function baseRow(Model $record): array
    {
        return ['id' => $record->getKey(), 'form' => []];
    }

    /** @return array<string, mixed> */
    private function field(string $key, string $label, string $type, array $options = []): array
    {
        return compact('key', 'label', 'type', 'options') + ['required' => false];
    }

    /** @return array<string, mixed> */
    private function bulkAction(string $value, string $label, array $options = [], bool $needsNote = false): array
    {
        return compact('value', 'label', 'options', 'needsNote');
    }

    /** @return array<int, array{label: string, value: string}> */
    private function valueOptions(array $values): array
    {
        return array_map(fn (string $value): array => ['label' => str($value)->headline()->toString(), 'value' => $value], $values);
    }

    /** @return array<int, array{label: string, value: string}> */
    private function enumOptions(array $values): array
    {
        return array_map(fn (BackedEnum $value): array => ['label' => str((string) $value->value)->headline()->toString(), 'value' => (string) $value->value], $values);
    }

    /** @return array<int, array{label: string, value: string}> */
    private function categoryOptions(string $resource): array
    {
        $values = match ($resource) {
            'database_backups' => DatabaseBackup::query()->distinct()->orderBy('driver')->pluck('driver'),
            'queue_jobs' => QueueJob::query()->distinct()->orderBy('queue')->pluck('queue'),
            'failed_jobs' => FailedJob::query()->distinct()->orderBy('queue')->pluck('queue'),
            'scheduler_heartbeats' => SchedulerHeartbeat::query()->distinct()->orderBy('name')->pluck('name'),
            'paddle_webhook_health' => PaymentWebhookEvent::query()->where('provider', 'paddle')->distinct()->orderBy('event_type')->pluck('event_type'),
            'application_logs' => ApplicationLogEntry::query()->distinct()->orderBy('channel')->pluck('channel'),
            'audit_logs' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'approval_histories' => ApprovalHistory::query()->distinct()->orderBy('decision')->pluck('decision'),
            'health_checks' => HealthCheckRun::query()->distinct()->orderBy('check_name')->pluck('check_name'),
            'operational_alerts' => OperationalAlert::query()->distinct()->orderBy('source')->pluck('source'),
            default => collect(),
        };

        return $values->filter()->values()->map(fn ($value): array => [
            'label' => str((string) $value)->headline()->toString(),
            'value' => (string) $value,
        ])->all();
    }

    private function categoryLabel(string $resource): string
    {
        return match ($resource) {
            'database_backups' => 'Driver',
            'queue_jobs', 'failed_jobs' => 'Queue',
            'scheduler_heartbeats' => 'Task',
            'paddle_webhook_health' => 'Event type',
            'application_logs' => 'Channel',
            'audit_logs' => 'Action',
            'approval_histories' => 'Decision',
            'health_checks' => 'Check',
            'operational_alerts' => 'Source',
            default => 'Group',
        };
    }

    private function queueHeartbeatAge(): int
    {
        $timestamp = Cache::get((string) config('operations.health.queue_heartbeat_key'));

        return is_numeric($timestamp) ? max(0, now()->timestamp - (int) $timestamp) : -1;
    }

    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? ($decoded['displayName'] ?? $decoded['job'] ?? 'Unknown job') : 'Unknown job';

        return class_basename((string) $name);
    }

    private function timestamp(?int $timestamp): ?string
    {
        return $timestamp === null ? null : now()->setTimestamp($timestamp)->toDateTimeString();
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateTimeString() : null;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function jsonSummary(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return str((string) json_encode($value, JSON_UNESCAPED_SLASHES))->limit(240)->toString();
    }
}
