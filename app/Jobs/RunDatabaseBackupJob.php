<?php

namespace App\Jobs;

use App\Services\Operations\DatabaseBackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly bool $verify = false)
    {
        $this->timeout = max(60, (int) config('operations.backups.timeout_seconds', 900) + 60);
        $this->onQueue('monitoring');
    }

    public function handle(DatabaseBackupManager $backups): void
    {
        $backups->run($this->verify);
    }
}
