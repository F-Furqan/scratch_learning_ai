<?php

namespace App\Services\Operations;

use App\Notifications\OperationalAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OperationalAlertNotifier
{
    /**
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public function notify(string $key, string $title, string $message, array $context = []): void
    {
        Log::channel('monitoring')->error($title, [
            'alert_key' => $key,
            'message' => $message,
            ...$context,
        ]);

        $cooldown = max(1, (int) config('operations.alerts.cooldown_minutes', 15));
        $cacheKey = 'operations.alert.'.hash('sha256', $key);

        try {
            if (! Cache::add($cacheKey, now()->timestamp, now()->addMinutes($cooldown))) {
                return;
            }
        } catch (\Throwable $exception) {
            Log::channel('monitoring')->warning('Operational alert deduplication is unavailable.', [
                'alert_key' => $key,
                'exception' => $exception::class,
            ]);
        }

        foreach ($this->recipients() as $recipient) {
            try {
                Notification::route('mail', $recipient)->notifyNow(
                    new OperationalAlertNotification($title, $message, $context),
                );
            } catch (\Throwable $exception) {
                Log::channel('monitoring')->error('Unable to deliver operational alert notification.', [
                    'alert_key' => $key,
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
        $configured = (string) config('operations.alerts.mail_to', '');

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
