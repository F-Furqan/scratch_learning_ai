<?php

use App\Jobs\AggregateAdReportsJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\ApplicationLogEntry;
use App\Models\HealthCheckRun;
use App\Models\SchedulerHeartbeat;
use App\Services\Creators\EditorialWorkflowService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('editorial:publish-due', function (EditorialWorkflowService $editorial): int {
    $count = $editorial->publishDue();

    $this->info("Published {$count} scheduled editorial item(s).");

    return Command::SUCCESS;
})->purpose('Publish due scheduled editorial content');

Schedule::job(new AggregateAdReportsJob)->hourly()->name('ads.aggregate-reports');
Schedule::job(new QueueHeartbeatJob)
    ->everyMinute()
    ->name('operations.queue-heartbeat')
    ->withoutOverlapping();
Schedule::command('platform:monitor')
    ->everyMinute()
    ->name('platform.monitor')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('03:00')
    ->name('queue.prune-failed')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::call(function (): void {
    ApplicationLogEntry::query()
        ->where('occurred_at', '<', now()->subDays(max(1, (int) config('operations.logging.database_retention_days', 14))))
        ->delete();
    HealthCheckRun::query()
        ->where('checked_at', '<', now()->subDays(max(1, (int) config('operations.logging.health_retention_days', 14))))
        ->delete();
    SchedulerHeartbeat::query()
        ->where('started_at', '<', now()->subDays(max(1, (int) config('operations.logging.scheduler_retention_days', 14))))
        ->delete();
})
    ->dailyAt('03:20')
    ->name('operations.prune-history')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('editorial:publish-due')
    ->everyFiveMinutes()
    ->name('editorial.publish-due')
    ->withoutOverlapping();
Schedule::command('platform:backup')
    ->dailyAt('02:10')
    ->name('platform.backup')
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn (): bool => (bool) config('operations.backups.enabled', false));
