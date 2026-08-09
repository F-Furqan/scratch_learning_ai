<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification
{
    public function __construct(
        private readonly string $stage,
        private readonly string $driver,
        private readonly string $connection,
        private readonly string $reason,
        private readonly ?int $backupId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(config('app.name').' database backup failure')
            ->line('A database backup operation failed.')
            ->line('Stage: '.$this->stage)
            ->line('Driver: '.$this->driver)
            ->line('Connection: '.$this->connection)
            ->line('Backup record: '.($this->backupId ?? 'unavailable'))
            ->line('Reason: '.$this->reason)
            ->line('Review the monitoring log and database backup audit history before retrying.');
    }
}
