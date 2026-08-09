<?php

namespace App\Services\Operations;

use App\Contracts\Operations\BackupDriver;
use App\Enums\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Support\Operations\BackupArtifact;
use Illuminate\Support\Facades\Log;

class BackupRetentionService
{
    /** @var array<string, BackupDriver> */
    private array $drivers = [];

    /**
     * @param  iterable<BackupDriver>  $drivers
     */
    public function __construct(
        iterable $drivers,
        private readonly BackupAuditLogger $auditLogger,
    ) {
        foreach ($drivers as $driver) {
            $this->drivers[$driver->name()] = $driver;
        }
    }

    public function prune(): int
    {
        $retentionDays = (int) config('operations.backups.retention_days', 14);

        if ($retentionDays <= 0) {
            return 0;
        }

        $pruned = 0;

        DatabaseBackup::query()
            ->where('status', DatabaseBackupStatus::Succeeded->value)
            ->whereNull('storage_deleted_at')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->orderBy('id')
            ->eachById(function (DatabaseBackup $backup) use (&$pruned): void {
                $driver = $this->drivers[$backup->driver] ?? null;

                if ($driver === null) {
                    Log::channel('monitoring')->warning('Unable to prune backup because its driver is unavailable.', [
                        'backup_id' => $backup->getKey(),
                        'driver' => $backup->driver,
                    ]);

                    return;
                }

                try {
                    $driver->delete(BackupArtifact::fromRecord($backup));
                    $backup->forceFill(['storage_deleted_at' => now()])->save();
                    $this->auditLogger->log('database_backup.pruned', $backup);
                    $pruned++;
                } catch (\Throwable $exception) {
                    Log::channel('monitoring')->warning('Database backup retention cleanup failed.', [
                        'backup_id' => $backup->getKey(),
                        'driver' => $backup->driver,
                        'exception' => $exception::class,
                    ]);
                }
            });

        return $pruned;
    }
}
