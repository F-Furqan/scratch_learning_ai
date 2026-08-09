<?php

namespace App\Services\Operations;

use App\Models\DatabaseBackup;
use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BackupFailureNotifier
{
    public function notify(string $stage, string $reason, string $driver, string $connection, ?DatabaseBackup $backup = null): void
    {
        Log::channel('monitoring')->error('Database backup operation failed.', [
            'stage' => $stage,
            'driver' => $driver,
            'connection' => $connection,
            'backup_id' => $backup?->getKey(),
            'reason' => $reason,
        ]);

        foreach ($this->recipients() as $recipient) {
            try {
                Notification::route('mail', $recipient)->notifyNow(new BackupFailedNotification(
                    stage: $stage,
                    driver: $driver,
                    connection: $connection,
                    reason: $reason,
                    backupId: $backup?->getKey(),
                ));
            } catch (\Throwable $exception) {
                Log::channel('monitoring')->error('Unable to deliver database backup failure notification.', [
                    'backup_id' => $backup?->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $configured = (string) config('operations.backups.failure_mail_to', '');

        if (blank($configured)) {
            $configured = (string) config('operations.alerts.mail_to', '');
        }

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
