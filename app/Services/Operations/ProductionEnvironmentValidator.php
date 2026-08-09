<?php

namespace App\Services\Operations;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductionEnvironmentValidator
{
    /**
     * @return list<array{name: string, passed: bool, details: string}>
     */
    public function validate(): array
    {
        $queueConnection = (string) config('queue.default');
        $queueRetryAfter = (int) config("queue.connections.{$queueConnection}.retry_after", 0);
        $workerTimeout = max(1, (int) config('operations.queue.worker_timeout_seconds', 120));
        $databaseConnection = (string) config('database.default');
        $databaseName = (string) config("database.connections.{$databaseConnection}.database", '');
        $loggingChannels = $this->loggingChannels();
        $paddleEnabled = config('payments.provider') === 'paddle';

        return [
            $this->check('application_environment', config('app.env') === 'production', 'APP_ENV must be production.'),
            $this->check('debug_mode', config('app.debug') === false, 'APP_DEBUG must be false.'),
            $this->check('application_url', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL must use HTTPS.'),
            $this->check('application_key', $this->validApplicationKey(), 'APP_KEY must be a non-placeholder encryption key.'),
            $this->check('database_name', $databaseName !== '' && ! str_contains(strtolower($databaseName), 'scratch-test'), 'Production must use a named non-test database.'),
            $this->check('queue_connection', in_array($queueConnection, ['database', 'redis', 'sqs', 'beanstalkd', 'failover'], true), 'QUEUE_CONNECTION must use a durable asynchronous backend.'),
            $this->check('queue_failed_driver', config('queue.failed.driver') === 'database-uuids', 'QUEUE_FAILED_DRIVER must retain failed jobs in the database.'),
            $this->check(
                'queue_retry_window',
                ! in_array($queueConnection, ['database', 'redis', 'beanstalkd'], true) || $queueRetryAfter > $workerTimeout,
                'Queue retry_after must be greater than QUEUE_WORKER_TIMEOUT.',
            ),
            $this->check('queue_heartbeat', (bool) config('operations.health.queue_heartbeat_required'), 'QUEUE_HEARTBEAT_REQUIRED must be true.'),
            $this->check('cache_store', ! in_array(config('cache.default'), ['array', 'null'], true), 'CACHE_STORE must be shared and persistent.'),
            $this->check('session_driver', ! in_array(config('session.driver'), ['array'], true), 'SESSION_DRIVER must persist between requests.'),
            $this->check('session_encryption', (bool) config('session.encrypt'), 'SESSION_ENCRYPT must be true.'),
            $this->check(
                'log_rotation',
                in_array('daily', $loggingChannels, true) || (bool) config('operations.logging.external_rotation'),
                'Use the daily channel or explicitly enable external log rotation.',
            ),
            $this->check('operations_alerts', filled(config('operations.alerts.mail_to')), 'OPERATIONS_ALERT_MAIL_TO must contain at least one recipient.'),
            $this->check('mail_transport', ! in_array(config('mail.default'), ['array', 'log'], true), 'MAIL_MAILER must deliver real production alerts.'),
            $this->check('backups_enabled', (bool) config('operations.backups.enabled'), 'BACKUPS_ENABLED must be true after restore verification.'),
            $this->check('backup_alerts', filled(config('operations.backups.failure_mail_to')) || filled(config('operations.alerts.mail_to')), 'Backup failures require an alert recipient.'),
            $this->check('paddle_environment', ! $paddleEnabled || config('payments.paddle.environment') === 'live', 'PADDLE_ENVIRONMENT must be live.'),
            $this->check('paddle_api_key', ! $paddleEnabled || filled(config('payments.paddle.api_key')), 'PADDLE_API_KEY is required.'),
            $this->check('paddle_webhook_secret', ! $paddleEnabled || filled(config('payments.paddle.webhook_secret')), 'PADDLE_WEBHOOK_SECRET is required.'),
            $this->directoryCheck('storage_permissions', storage_path()),
            $this->directoryCheck('cache_permissions', base_path('bootstrap/cache')),
            $this->storageDiskCheck(),
        ];
    }

    /**
     * @param  list<array{name: string, passed: bool, details: string}>  $results
     */
    public function passes(array $results): bool
    {
        return collect($results)->every(fn (array $result): bool => $result['passed']);
    }

    /**
     * @return array{name: string, passed: bool, details: string}
     */
    private function check(string $name, bool $passed, string $details): array
    {
        return compact('name', 'passed', 'details');
    }

    private function validApplicationKey(): bool
    {
        $key = (string) config('app.key');

        return strlen($key) >= 32
            && $key !== 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
    }

    /**
     * @return list<string>
     */
    private function loggingChannels(): array
    {
        $default = (string) config('logging.default');

        if ($default !== 'stack') {
            return [$default];
        }

        $channels = config('logging.channels.stack.channels', []);

        return is_array($channels)
            ? array_values(array_filter($channels, 'is_string'))
            : [];
    }

    /**
     * @return array{name: string, passed: bool, details: string}
     */
    private function directoryCheck(string $name, string $path): array
    {
        return $this->check(
            $name,
            is_dir($path) && is_readable($path) && is_writable($path),
            "Directory [{$path}] must exist and be readable and writable by the application user.",
        );
    }

    /**
     * @return array{name: string, passed: bool, details: string}
     */
    private function storageDiskCheck(): array
    {
        $diskName = (string) (config('operations.health.storage_disk') ?: config('filesystems.default'));
        $path = 'healthchecks/production-validation-'.Str::uuid()->toString();

        try {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);
            $written = $disk->put($path, 'production-readiness');
            $readable = $written && $disk->get($path) === 'production-readiness';

            return $this->check('storage_disk', $readable, "Storage disk [{$diskName}] must support write, read, and delete operations.");
        } catch (\Throwable) {
            return $this->check('storage_disk', false, "Storage disk [{$diskName}] must support write, read, and delete operations.");
        } finally {
            if (isset($disk)) {
                try {
                    $disk->delete($path);
                } catch (\Throwable) {
                    // The failed readiness result already captures an unavailable disk.
                }
            }
        }
    }
}
