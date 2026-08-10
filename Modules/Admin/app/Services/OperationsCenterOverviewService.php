<?php

namespace Modules\Admin\Services;

use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use App\Models\ApplicationLogEntry;
use App\Models\DatabaseBackup;
use App\Models\FailedJob;
use App\Models\HealthCheckRun;
use App\Models\OperationalAlert;
use App\Models\PaymentWebhookEvent;
use App\Models\QueueJob;
use App\Models\SchedulerHeartbeat;
use BackedEnum;
use Illuminate\Support\Facades\Cache;

final class OperationsCenterOverviewService
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $backup = DatabaseBackup::query()->latest('started_at')->first();
        $scheduler = SchedulerHeartbeat::query()->latest('started_at')->first();
        $heartbeatAt = Cache::get((string) config('operations.health.queue_heartbeat_key'));
        $heartbeatAge = is_numeric($heartbeatAt) ? max(0, now()->timestamp - (int) $heartbeatAt) : null;
        $staleSeconds = max(60, (int) config('operations.health.queue_heartbeat_max_age_seconds', 180));
        $queueHeartbeatRequired = (bool) config('operations.health.queue_heartbeat_required', true);
        $queueHealthy = ! $queueHeartbeatRequired || ($heartbeatAge !== null && $heartbeatAge <= $staleSeconds);
        $schedulerAge = $scheduler?->started_at?->diffInSeconds(now());
        $schedulerStaleSeconds = max(60, (int) config('operations.health.scheduler_heartbeat_max_age_seconds', 180));
        $schedulerHealthy = $scheduler?->status === 'healthy'
            && $schedulerAge !== null
            && $schedulerAge <= $schedulerStaleSeconds;
        $failedJobs = FailedJob::query()->count();
        $failedWebhooks = PaymentWebhookEvent::query()->where('provider', 'paddle')->where('status', 'failed')->count();
        $openAlerts = OperationalAlert::query()->whereIn('status', ['open', 'acknowledged'])->count();
        $health = HealthCheckRun::query()
            ->latest('checked_at')
            ->limit(100)
            ->get()
            ->unique('check_name')
            ->values();
        $needsAttention = $openAlerts > 0
            || $failedJobs > 0
            || $failedWebhooks > 0
            || ! $queueHealthy
            || ! $schedulerHealthy
            || $health->contains(fn (HealthCheckRun $run): bool => $run->status === 'unhealthy');

        return [
            'status' => $needsAttention ? 'attention' : 'operational',
            'generatedAt' => now()->toISOString(),
            'cards' => [
                $this->card('Backups', $this->enumValue($backup?->status) ?: 'not recorded', $backup?->status === DatabaseBackupStatus::Succeeded ? 'healthy' : 'warning', $backup?->completed_at?->diffForHumans(), '/admin/operations/backups'),
                $this->card('Queue', QueueJob::query()->count().' pending', $queueHealthy ? 'healthy' : 'critical', $this->queueHeartbeatDetail($queueHeartbeatRequired, $heartbeatAge), '/admin/operations/queue'),
                $this->card('Failed jobs', (string) $failedJobs, $failedJobs > 0 ? 'critical' : 'healthy', 'Unresolved queue failures', '/admin/operations/failed-jobs'),
                $this->card('Scheduler', $scheduler?->status ?? 'not recorded', $this->schedulerTone($scheduler?->status, $schedulerAge, $schedulerStaleSeconds), $scheduler?->started_at?->diffForHumans(), '/admin/operations/scheduler'),
                $this->card('Paddle webhooks', $failedWebhooks.' failed', $failedWebhooks > 0 ? 'critical' : 'healthy', PaymentWebhookEvent::query()->where('provider', 'paddle')->count().' events', '/admin/operations/paddle-webhooks'),
                $this->card('Alerts', (string) $openAlerts, $openAlerts > 0 ? 'warning' : 'healthy', 'Open or acknowledged', '/admin/operations/alerts'),
            ],
            'health' => $health
                ->map(fn (HealthCheckRun $run): array => [
                    'name' => $run->check_name,
                    'status' => $run->status,
                    'checkedAt' => $run->checked_at?->toISOString(),
                    'details' => $run->metadata ?? [],
                ])->all(),
            'recentAlerts' => OperationalAlert::query()
                ->whereIn('status', ['open', 'acknowledged'])
                ->latest('last_detected_at')
                ->limit(8)
                ->get()
                ->map(fn (OperationalAlert $alert): array => [
                    'id' => $alert->getKey(),
                    'title' => $alert->title,
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                    'source' => $alert->source,
                    'lastDetectedAt' => $alert->last_detected_at?->toISOString(),
                ])->all(),
            'counts' => [
                'errors24h' => ApplicationLogEntry::query()->whereIn('level', ['error', 'critical', 'alert', 'emergency'])->where('occurred_at', '>=', now()->subDay())->count(),
                'unhealthyChecks24h' => HealthCheckRun::query()->where('status', 'unhealthy')->where('checked_at', '>=', now()->subDay())->count(),
                'verifiedBackups' => DatabaseBackup::query()->where('verification_status', BackupVerificationStatus::Verified->value)->count(),
            ],
        ];
    }

    /** @return array<string, string|null> */
    private function card(string $label, string $value, string $tone, ?string $detail, string $href): array
    {
        return compact('label', 'value', 'tone', 'detail', 'href');
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function queueHeartbeatDetail(bool $required, ?int $age): string
    {
        if (! $required) {
            return 'Worker heartbeat not required';
        }

        return $age === null ? 'No worker heartbeat' : "Heartbeat {$age}s ago";
    }

    private function schedulerTone(?string $status, ?int $age, int $staleSeconds): string
    {
        if ($status === 'warning' && $age !== null && $age <= $staleSeconds) {
            return 'warning';
        }

        return $status === 'healthy' && $age !== null && $age <= $staleSeconds
            ? 'healthy'
            : 'critical';
    }
}
