<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Log;

class BackupAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, DatabaseBackup $backup, array $metadata = []): void
    {
        try {
            AuditLog::query()->create([
                'action' => $action,
                'auditable_type' => $backup->getMorphClass(),
                'auditable_id' => $backup->getKey(),
                'metadata' => [
                    'driver' => $backup->driver,
                    'connection' => $backup->connection_name,
                    'status' => $backup->status->value,
                    ...$metadata,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::channel('monitoring')->warning('Unable to persist database backup audit log.', [
                'backup_id' => $backup->getKey(),
                'action' => $action,
                'exception' => $exception::class,
            ]);
        }
    }
}
