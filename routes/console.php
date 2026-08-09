<?php

use App\Jobs\AggregateAdReportsJob;
use App\Services\Creators\EditorialWorkflowService;
use App\Services\Operations\DatabaseBackupManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:backup', function (DatabaseBackupManager $backups): int {
    $result = $backups->run();

    $this->info("Database backup written to [{$result['disk']}:{$result['path']}] ({$result['bytes']} bytes).");

    return Command::SUCCESS;
})->purpose('Create a database backup for local SQLite deployments');

Artisan::command('editorial:publish-due', function (EditorialWorkflowService $editorial): int {
    $count = $editorial->publishDue();

    $this->info("Published {$count} scheduled editorial item(s).");

    return Command::SUCCESS;
})->purpose('Publish due scheduled editorial content');

Schedule::job(new AggregateAdReportsJob)->hourly()->name('ads.aggregate-reports');
Schedule::command('editorial:publish-due')
    ->everyFiveMinutes()
    ->name('editorial.publish-due')
    ->withoutOverlapping();
Schedule::command('platform:backup')
    ->dailyAt('02:10')
    ->name('platform.backup')
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('operations.backups.enabled', true));
