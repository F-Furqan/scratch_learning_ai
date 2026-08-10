<?php

namespace App\Services\Operations;

use App\Enums\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Models\PaymentWebhookEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalMonitor
{
    public function __construct(
        private readonly OperationalAlertNotifier $notifier,
        private readonly OperationalAlertStore $alerts,
    ) {}

    /**
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    public function inspect(): array
    {
        return [
            ...$this->guarded('queue.monitor_failed', fn (): array => $this->failedJobIssues()),
            ...$this->guarded('paddle.monitor_failed', fn (): array => $this->paddleIssues()),
            ...$this->guarded('backup.monitor_failed', fn (): array => $this->backupIssues()),
        ];
    }

    /**
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    public function run(bool $sendAlerts = true): array
    {
        $issues = $this->inspect();

        if ($sendAlerts) {
            foreach ($issues as $issue) {
                $this->notifier->notify(
                    $issue['key'],
                    $issue['title'],
                    $issue['message'],
                    $issue['context'],
                    'monitor',
                );
            }
        }

        $activeKeys = [];
        foreach ($issues as $issue) {
            $activeKeys[] = $issue['key'];
        }

        $this->alerts->resolveMissingMonitorAlerts($activeKeys);

        return $issues;
    }

    /**
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    private function failedJobIssues(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [$this->issue(
                'queue.failed_jobs_table_missing',
                'Failed-job storage is unavailable',
                'The failed_jobs table is missing, so queue failures cannot be retained or monitored.',
            )];
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $threshold = max(0, (int) config('operations.monitoring.failed_jobs_threshold', 0));

        if ($failedJobs <= $threshold) {
            return [];
        }

        return [$this->issue(
            'queue.failed_jobs',
            'Failed queue jobs require attention',
            "The failed_jobs table contains {$failedJobs} unresolved job(s).",
            ['failed_jobs' => $failedJobs, 'threshold' => $threshold],
        )];
    }

    /**
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    private function paddleIssues(): array
    {
        if (! Schema::hasTable('payment_webhook_events')) {
            return [$this->issue(
                'paddle.webhook_table_missing',
                'Paddle webhook monitoring is unavailable',
                'The payment_webhook_events table is missing.',
            )];
        }

        $issues = [];
        $window = max(5, (int) config('operations.monitoring.paddle_failure_window_minutes', 60));
        $staleMinutes = max(2, (int) config('operations.monitoring.paddle_stale_minutes', 10));
        $failed = PaymentWebhookEvent::query()
            ->where('provider', 'paddle')
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes($window))
            ->count();
        $stale = PaymentWebhookEvent::query()
            ->where('provider', 'paddle')
            ->whereIn('status', ['accepted', 'processing'])
            ->where('queued_at', '<=', now()->subMinutes($staleMinutes))
            ->count();

        if ($failed > 0) {
            $issues[] = $this->issue(
                'paddle.webhooks_failed',
                'Paddle webhook processing failures detected',
                "{$failed} Paddle webhook event(s) failed during the monitoring window.",
                ['failed_events' => $failed, 'window_minutes' => $window],
            );
        }

        if ($stale > 0) {
            $issues[] = $this->issue(
                'paddle.webhooks_stale',
                'Paddle webhook processing is stalled',
                "{$stale} accepted or processing Paddle webhook event(s) exceeded the queue deadline.",
                ['stale_events' => $stale, 'stale_minutes' => $staleMinutes],
            );
        }

        return $issues;
    }

    /**
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    private function backupIssues(): array
    {
        if (! (bool) config('operations.backups.enabled', false)) {
            return [];
        }

        if (! Schema::hasTable('database_backups')) {
            return [$this->issue(
                'backup.table_missing',
                'Database backup monitoring is unavailable',
                'The database_backups table is missing.',
            )];
        }

        $latest = DatabaseBackup::query()->latest('started_at')->first();

        if ($latest === null) {
            return [$this->issue(
                'backup.never_succeeded',
                'No database backup has been recorded',
                'Backups are enabled but no backup record exists.',
            )];
        }

        if ($latest->status === DatabaseBackupStatus::Failed) {
            return [$this->issue(
                'backup.latest_failed',
                'The latest database backup failed',
                'The most recent database backup record is marked as failed.',
                ['backup_id' => $latest->getKey()],
            )];
        }

        $maximumAge = max(1, (int) config('operations.monitoring.backup_max_age_hours', 26));
        $latestSuccessful = DatabaseBackup::query()
            ->where('status', DatabaseBackupStatus::Succeeded->value)
            ->latest('completed_at')
            ->first();
        $completedAt = $latestSuccessful?->getAttribute('completed_at');

        if (! $completedAt instanceof CarbonInterface || $completedAt->lt(now()->subHours($maximumAge))) {
            return [$this->issue(
                'backup.stale',
                'The latest database backup is stale',
                "No successful database backup completed within {$maximumAge} hours.",
                ['backup_id' => $latestSuccessful?->getKey(), 'maximum_age_hours' => $maximumAge],
            )];
        }

        return [];
    }

    /**
     * @param  callable(): list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>  $inspection
     * @return list<array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}>
     */
    private function guarded(string $key, callable $inspection): array
    {
        try {
            return $inspection();
        } catch (\Throwable $exception) {
            return [$this->issue(
                $key,
                'Operational monitor could not complete',
                'An operational inspection failed before it could determine service health.',
                ['exception' => $exception::class],
            )];
        }
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $context
     * @return array{key: string, title: string, message: string, context: array<string, bool|float|int|string|null>}
     */
    private function issue(string $key, string $title, string $message, array $context = []): array
    {
        return compact('key', 'title', 'message', 'context');
    }
}
