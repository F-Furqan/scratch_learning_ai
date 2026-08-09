<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalAlertNotification extends Notification
{
    /**
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly array $context = [],
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
        $mail = (new MailMessage)
            ->error()
            ->subject(config('app.name').' operational alert: '.$this->title)
            ->line($this->message);

        foreach ($this->context as $key => $value) {
            $mail->line(str_replace('_', ' ', ucfirst($key)).': '.$this->display($value));
        }

        return $mail->line('Review the monitoring log and platform health endpoint before retrying or deploying.');
    }

    private function display(bool|float|int|string|null $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'yes' : 'no',
            $value === null => 'unavailable',
            default => (string) $value,
        };
    }
}
