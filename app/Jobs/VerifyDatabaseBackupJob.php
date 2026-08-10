<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Services\Operations\DatabaseBackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $backupId)
    {
        $this->timeout = max(60, (int) config('operations.backups.timeout_seconds', 900) + 60);
        $this->onQueue('monitoring');
    }

    public function handle(DatabaseBackupManager $backups): void
    {
        $backups->verifyExisting(DatabaseBackup::query()->findOrFail($this->backupId));
    }
}
