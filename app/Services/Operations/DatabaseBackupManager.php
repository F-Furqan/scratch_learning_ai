<?php

namespace App\Services\Operations;

use App\Contracts\Operations\BackupDriver;
use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DatabaseBackupManager
{
    /** @var array<string, BackupDriver> */
    private array $drivers = [];

    /**
     * @param  iterable<BackupDriver>  $drivers
     */
    public function __construct(
        iterable $drivers,
        private readonly BackupRetentionService $retention,
        private readonly BackupAuditLogger $auditLogger,
        private readonly BackupFailureNotifier $failureNotifier,
    ) {
        foreach ($drivers as $driver) {
            $this->drivers[$driver->name()] = $driver;
        }
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function plan(bool $verify = false): array
    {
        $context = $this->context();

        return [
            'enabled' => (bool) config('operations.backups.enabled', false),
            'driver' => $this->driverName($context),
            'connection' => $context->connectionName,
            'database' => $context->databaseName(),
            'disk' => $context->disk,
            'path' => $context->path,
            'retention_days' => (int) config('operations.backups.retention_days', 14),
            'verify' => $verify,
            'verification_database' => $verify ? $context->verificationDatabase : 'not requested',
        ];
    }

    public function run(bool $verify = false): DatabaseBackup
    {
        if (! (bool) config('operations.backups.enabled', false)) {
            throw new RuntimeException('Database backups are disabled. Set BACKUPS_ENABLED=true after validating the dry run.');
        }

        $context = $this->context();
        $driver = $this->resolveDriver($context);

        try {
            $backup = DatabaseBackup::query()->create([
                'driver' => $driver->name(),
                'connection_name' => $context->connectionName,
                'database_name' => $context->databaseName(),
                'status' => DatabaseBackupStatus::Running,
                'verification_status' => $verify
                    ? BackupVerificationStatus::Pending
                    : BackupVerificationStatus::NotRequested,
                'started_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $reason = $this->safeReason($exception, $context);
            $this->failureNotifier->notify('record', $reason, $driver->name(), $context->connectionName);

            throw new RuntimeException('Unable to start the database backup record: '.$reason, previous: $exception);
        }

        try {
            $artifact = $driver->create($context);

            $backup->forceFill([
                'status' => DatabaseBackupStatus::Succeeded,
                'disk' => $artifact->disk,
                'path' => $artifact->path,
                'provider_reference' => $artifact->providerReference,
                'bytes' => $artifact->bytes,
                'checksum' => $artifact->checksum,
                'metadata' => $artifact->metadata,
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log('database_backup.succeeded', $backup, [
                'bytes' => $artifact->bytes,
                'checksum' => $artifact->checksum,
            ]);
        } catch (\Throwable $exception) {
            $reason = $this->safeReason($exception, $context);
            $this->recordCreationFailure($backup, $reason);
            $this->failureNotifier->notify('create', $reason, $driver->name(), $context->connectionName, $backup);

            throw new RuntimeException('Database backup failed: '.$reason, previous: $exception);
        }

        $this->retention->prune();

        if (! $verify) {
            return $backup->refresh();
        }

        try {
            $verification = $driver->verify($context, $artifact);

            if (! $verification->verified) {
                throw new RuntimeException('The backup driver did not confirm restore verification.');
            }

            $backup->forceFill([
                'verification_status' => BackupVerificationStatus::Verified,
                'verified_at' => now(),
                'metadata' => [...($backup->metadata ?? []), 'verification' => $verification->metadata],
            ])->save();

            $this->auditLogger->log('database_backup.verified', $backup, $verification->metadata);
        } catch (\Throwable $exception) {
            $reason = $this->safeReason($exception, $context);
            $backup->forceFill([
                'verification_status' => BackupVerificationStatus::Failed,
                'verification_failure_reason' => $reason,
            ])->save();

            $this->auditLogger->log('database_backup.verification_failed', $backup, ['reason' => $reason]);
            $this->failureNotifier->notify('verify', $reason, $driver->name(), $context->connectionName, $backup);

            throw new RuntimeException('Database backup verification failed: '.$reason, previous: $exception);
        }

        return $backup->refresh();
    }

    public function verifyExisting(DatabaseBackup $backup): DatabaseBackup
    {
        if ($backup->status !== DatabaseBackupStatus::Succeeded) {
            throw new RuntimeException('Only successful database backups can be restore-verified.');
        }

        $context = $this->context();
        $driver = $this->drivers[$backup->driver] ?? null;

        if ($driver === null) {
            throw new RuntimeException("Database backup driver [{$backup->driver}] is not registered.");
        }

        $backup->forceFill([
            'verification_status' => BackupVerificationStatus::Pending,
            'verification_failure_reason' => null,
        ])->save();

        try {
            $verification = $driver->verify($context, BackupArtifact::fromRecord($backup));

            if (! $verification->verified) {
                throw new RuntimeException('The backup driver did not confirm restore verification.');
            }

            $backup->forceFill([
                'verification_status' => BackupVerificationStatus::Verified,
                'verified_at' => now(),
                'metadata' => [...($backup->metadata ?? []), 'verification' => $verification->metadata],
            ])->save();

            $this->auditLogger->log('database_backup.verified', $backup, $verification->metadata);
        } catch (\Throwable $exception) {
            $reason = $this->safeReason($exception, $context);
            $backup->forceFill([
                'verification_status' => BackupVerificationStatus::Failed,
                'verification_failure_reason' => $reason,
            ])->save();

            $this->auditLogger->log('database_backup.verification_failed', $backup, ['reason' => $reason]);
            $this->failureNotifier->notify('verify', $reason, $driver->name(), $context->connectionName, $backup);

            throw new RuntimeException('Database backup verification failed: '.$reason, previous: $exception);
        }

        return $backup->refresh();
    }

    private function context(): BackupContext
    {
        $connectionName = (string) (config('operations.backups.connection') ?: config('database.default'));
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ($connection['database'] ?? '') === '') {
            throw new RuntimeException("Backup database connection [{$connectionName}] is not configured.");
        }

        $disk = (string) config('operations.backups.disk', 'local');
        $this->assertPrivateDisk($disk);

        return new BackupContext(
            connectionName: $connectionName,
            connection: $connection,
            disk: $disk,
            path: trim((string) config('operations.backups.path', 'backups/database'), '/'),
            verificationDatabase: (string) config('operations.backups.verification_database', 'scratch-restore-test'),
            timeoutSeconds: max(30, (int) config('operations.backups.timeout_seconds', 900)),
        );
    }

    private function resolveDriver(BackupContext $context): BackupDriver
    {
        $name = $this->driverName($context);
        $driver = $this->drivers[$name] ?? null;

        if ($driver === null) {
            throw new RuntimeException("Database backup driver [{$name}] is not registered.");
        }

        return $driver;
    }

    private function driverName(BackupContext $context): string
    {
        $configured = (string) config('operations.backups.driver', 'auto');

        if ($configured !== 'auto') {
            return $configured;
        }

        return match ($context->databaseDriver()) {
            'sqlite' => 'sqlite',
            'mysql', 'mariadb' => 'mysql',
            default => throw new RuntimeException("No automatic backup driver is available for [{$context->databaseDriver()}]."),
        };
    }

    private function assertPrivateDisk(string $disk): void
    {
        $configuration = config("filesystems.disks.{$disk}");

        if (! is_array($configuration)) {
            throw new RuntimeException("Backup filesystem disk [{$disk}] is not configured.");
        }

        if ($disk === 'public' || ($configuration['visibility'] ?? null) === 'public') {
            throw new RuntimeException('Database backups must use a private filesystem disk.');
        }

        if (($configuration['driver'] ?? null) !== 'local') {
            return;
        }

        $configuredRoot = (string) ($configuration['root'] ?? '');
        $absoluteRoot = str_starts_with($configuredRoot, DIRECTORY_SEPARATOR)
            ? $configuredRoot
            : base_path($configuredRoot);
        $root = rtrim($absoluteRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $public = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($root === DIRECTORY_SEPARATOR || str_starts_with($root, $public)) {
            throw new RuntimeException('Database backups cannot be stored inside the public directory.');
        }
    }

    private function safeReason(\Throwable $exception, BackupContext $context): string
    {
        $message = $exception->getMessage();
        $password = (string) ($context->connection['password'] ?? '');

        if ($password !== '') {
            $message = str_replace($password, '[REDACTED]', $message);
        }

        return mb_substr($message, 0, 4000);
    }

    private function recordCreationFailure(DatabaseBackup $backup, string $reason): void
    {
        try {
            $backup->forceFill([
                'status' => DatabaseBackupStatus::Failed,
                'failure_reason' => $reason,
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log('database_backup.failed', $backup, ['reason' => $reason]);
        } catch (\Throwable $exception) {
            Log::channel('monitoring')->error('Unable to persist database backup failure state.', [
                'backup_id' => $backup->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
