<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalMonitor;
use Illuminate\Console\Command;

class PlatformMonitorCommand extends Command
{
    protected $signature = 'platform:monitor
        {--no-alerts : Inspect operational state without delivering notifications}';

    protected $description = 'Inspect failed jobs, Paddle webhooks, and database backup readiness';

    public function handle(OperationalMonitor $monitor): int
    {
        $issues = $monitor->run(! (bool) $this->option('no-alerts'));

        if ($issues === []) {
            $this->components->info('Operational monitoring checks passed.');

            return self::SUCCESS;
        }

        $this->components->error(count($issues).' operational issue(s) require attention.');
        $this->table(
            ['Key', 'Issue', 'Details'],
            collect($issues)->map(fn (array $issue): array => [
                $issue['key'],
                $issue['title'],
                $issue['message'],
            ])->all(),
        );

        return self::FAILURE;
    }
}
