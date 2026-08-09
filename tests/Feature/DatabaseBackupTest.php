<?php

namespace Tests\Feature;

use App\Contracts\Operations\BackupDriver;
use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Notifications\BackupFailedNotification;
use App\Services\Operations\BackupAuditLogger;
use App\Services\Operations\BackupFailureNotifier;
use App\Services\Operations\BackupRetentionService;
use App\Services\Operations\DatabaseBackupManager;
use App\Services\Operations\MySqlBackupDriver;
use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_scheduler_runs_only_when_backups_are_enabled(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === 'platform.backup');

        $this->assertNotNull($event);
        $this->assertSame('10 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);

        config()->set('operations.backups.enabled', false);
        $this->assertFalse($event->filtersPass(app()));

        config()->set('operations.backups.enabled', true);
        $this->assertTrue($event->filtersPass(app()));
    }

    public function test_dry_run_resolves_the_plan_while_backups_are_disabled_without_side_effects(): void
    {
        config()->set('operations.backups.enabled', false);
        Process::fake();

        $this->artisan('platform:backup', ['--dry-run' => true, '--verify' => true])
            ->expectsOutputToContain('Backup dry run completed')
            ->expectsTable(
                ['Setting', 'Resolved value'],
                [
                    ['enabled', 'no'],
                    ['driver', 'mysql'],
                    ['connection', 'mysql'],
                    ['database', 'scratch-test'],
                    ['disk', 'local'],
                    ['path', 'backups/database'],
                    ['retention days', '14'],
                    ['verify', 'yes'],
                    ['verification database', 'scratch-restore-test'],
                ],
            )
            ->assertSuccessful();

        Process::assertNothingRan();
        $this->assertDatabaseCount(DatabaseBackup::class, 0);
    }

    public function test_disabled_backup_refuses_to_run(): void
    {
        config()->set('operations.backups.enabled', false);
        Process::fake();

        $this->artisan('platform:backup')
            ->expectsOutputToContain('Database backups are disabled')
            ->assertFailed();

        Process::assertNothingRan();
        $this->assertDatabaseCount(DatabaseBackup::class, 0);
    }

    public function test_mysql_dump_is_transaction_safe_compressed_checksummed_and_has_no_password_argument(): void
    {
        Storage::fake('local');
        $password = 'never-print-this-password';
        $context = $this->mysqlContext($password);

        Process::fake(function (PendingProcess $process) {
            $resultFile = collect($process->command)
                ->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));

            File::put(substr((string) $resultFile, strlen('--result-file=')), "CREATE TABLE lessons (id BIGINT);\n");

            return Process::result();
        });

        $artifact = app(MySqlBackupDriver::class)->create($context);

        Storage::disk('local')->assertExists((string) $artifact->path);
        $this->assertNotNull($artifact->checksum);
        $this->assertSame(64, strlen((string) $artifact->checksum));
        $this->assertGreaterThan(0, $artifact->bytes);
        $this->assertSame(
            "CREATE TABLE lessons (id BIGINT);\n",
            gzdecode((string) Storage::disk('local')->get((string) $artifact->path)),
        );

        Process::assertRan(function (PendingProcess $process) use ($password): bool {
            $command = implode(' ', $process->command);

            return in_array('--single-transaction', $process->command, true)
                && in_array('--routines', $process->command, true)
                && in_array('--triggers', $process->command, true)
                && in_array('--events', $process->command, true)
                && ! str_contains($command, $password)
                && ! str_contains($command, '--password');
        });
    }

    public function test_mysql_verification_restores_and_removes_only_the_disposable_database(): void
    {
        Storage::fake('local');
        $compressed = gzencode("CREATE TABLE lessons (id BIGINT);\n", 9);
        $path = 'backups/database/verification.sql.gz';
        Storage::disk('local')->put($path, $compressed);
        $artifact = new BackupArtifact(
            disk: 'local',
            path: $path,
            bytes: strlen($compressed),
            checksum: hash('sha256', $compressed),
        );

        Process::fake(function (PendingProcess $process) {
            $command = implode(' ', $process->command);

            return str_contains($command, 'SHOW TABLES')
                ? Process::result(output: "lessons\nusers\n")
                : Process::result();
        });

        $verification = app(MySqlBackupDriver::class)->verify($this->mysqlContext(), $artifact);

        $this->assertTrue($verification->verified);
        $this->assertSame('scratch-restore-test', $verification->metadata['restore_database']);
        $this->assertSame(2, $verification->metadata['table_count']);
        Process::assertRanTimes(fn (PendingProcess $process): bool => true, 5);
        Process::assertRan(fn (PendingProcess $process): bool => str_contains(
            implode(' ', $process->command),
            'DROP DATABASE IF EXISTS `scratch-restore-test`',
        ));
    }

    public function test_mysql_verification_refuses_to_overwrite_a_preexisting_disposable_database(): void
    {
        Storage::fake('local');
        $compressed = gzencode('SELECT 1;', 9);
        Storage::disk('local')->put('backups/database/existing.sql.gz', $compressed);

        Process::fake(function (PendingProcess $process) {
            $command = implode(' ', $process->command);

            return str_contains($command, 'information_schema.SCHEMATA')
                ? Process::result(output: "scratch-restore-test\n")
                : Process::result();
        });

        try {
            app(MySqlBackupDriver::class)->verify($this->mysqlContext(), new BackupArtifact(
                disk: 'local',
                path: 'backups/database/existing.sql.gz',
                bytes: strlen($compressed),
                checksum: hash('sha256', $compressed),
            ));
            $this->fail('Verification should refuse a pre-existing disposable database.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }

        Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
        Process::assertNotRan(fn (PendingProcess $process): bool => str_contains(
            implode(' ', $process->command),
            'DROP DATABASE',
        ));
    }

    public function test_mysql_verification_refuses_an_unsafe_restore_database_name(): void
    {
        Storage::fake('local');
        Process::fake();
        $compressed = gzencode('SELECT 1;', 9);
        Storage::disk('local')->put('backups/database/unsafe.sql.gz', $compressed);
        $context = new BackupContext(
            connectionName: 'mysql',
            connection: $this->mysqlContext()->connection,
            disk: 'local',
            path: 'backups/database',
            verificationDatabase: 'scratch_final',
            timeoutSeconds: 60,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('end in restore-test');

        try {
            app(MySqlBackupDriver::class)->verify($context, new BackupArtifact(
                disk: 'local',
                path: 'backups/database/unsafe.sql.gz',
                bytes: strlen($compressed),
                checksum: hash('sha256', $compressed),
            ));
        } finally {
            Process::assertNothingRan();
        }
    }

    public function test_sqlite_backup_is_recorded_audited_and_restore_verified(): void
    {
        Storage::fake('local');
        $source = storage_path('app/backup-test-source.sqlite');
        File::delete($source);

        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('CREATE TABLE lessons (id INTEGER PRIMARY KEY, title TEXT)');
        $pdo->exec("INSERT INTO lessons (title) VALUES ('Safe restore')");
        $pdo = null;

        config()->set('database.connections.backup_sqlite', [
            'driver' => 'sqlite',
            'database' => $source,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('operations.backups.enabled', true);
        config()->set('operations.backups.connection', 'backup_sqlite');
        config()->set('operations.backups.driver', 'auto');
        config()->set('operations.backups.disk', 'local');

        try {
            $backup = app(DatabaseBackupManager::class)->run(verify: true);
        } finally {
            File::delete($source);
        }

        $this->assertSame(DatabaseBackupStatus::Succeeded, $backup->status);
        $this->assertSame(BackupVerificationStatus::Verified, $backup->verification_status);
        $this->assertNotNull($backup->checksum);
        $this->assertNotNull($backup->verified_at);
        Storage::disk('local')->assertExists((string) $backup->path);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'database_backup.succeeded',
            'auditable_id' => $backup->getKey(),
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'database_backup.verified',
            'auditable_id' => $backup->getKey(),
        ]);
    }

    public function test_failed_backup_redacts_password_records_failure_and_notifies_operations(): void
    {
        Storage::fake('local');
        Notification::fake();
        config()->set('operations.backups.enabled', true);
        config()->set('operations.backups.connection', 'backup_failure_mysql');
        config()->set('operations.backups.driver', 'mysql');
        config()->set('operations.backups.failure_mail_to', 'ops@example.com');
        config()->set('database.connections.backup_failure_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'backup-source',
            'username' => 'backup-user',
            'password' => 'sensitive-backup-password',
            'unix_socket' => '',
        ]);

        Process::fake([
            '*' => Process::result(errorOutput: 'Access denied for sensitive-backup-password', exitCode: 1),
        ]);

        try {
            app(DatabaseBackupManager::class)->run();
            $this->fail('The backup was expected to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('sensitive-backup-password', $exception->getMessage());
        }

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame(DatabaseBackupStatus::Failed, $backup->status);
        $this->assertStringContainsString('[REDACTED]', (string) $backup->failure_reason);
        $this->assertStringNotContainsString('sensitive-backup-password', (string) $backup->failure_reason);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'database_backup.failed',
            'auditable_id' => $backup->getKey(),
        ]);
        Notification::assertSentOnDemand(BackupFailedNotification::class);
    }

    public function test_restore_verification_failure_is_recorded_audited_and_notified(): void
    {
        Notification::fake();
        config()->set('operations.backups.enabled', true);
        config()->set('operations.backups.driver', 'verification_failure');
        config()->set('operations.backups.failure_mail_to', 'ops@example.com');

        $driver = new class implements BackupDriver
        {
            public function name(): string
            {
                return 'verification_failure';
            }

            public function create(BackupContext $context): BackupArtifact
            {
                return new BackupArtifact(bytes: 100, checksum: str_repeat('a', 64));
            }

            public function verify(BackupContext $context, BackupArtifact $artifact): BackupVerification
            {
                throw new RuntimeException('Disposable restore rejected the dump.');
            }

            public function delete(BackupArtifact $artifact): void {}
        };
        $auditLogger = app(BackupAuditLogger::class);
        $manager = new DatabaseBackupManager(
            [$driver],
            new BackupRetentionService([$driver], $auditLogger),
            $auditLogger,
            app(BackupFailureNotifier::class),
        );

        try {
            $manager->run(verify: true);
            $this->fail('Restore verification was expected to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('verification failed', $exception->getMessage());
        }

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame(DatabaseBackupStatus::Succeeded, $backup->status);
        $this->assertSame(BackupVerificationStatus::Failed, $backup->verification_status);
        $this->assertStringContainsString('Disposable restore rejected', (string) $backup->verification_failure_reason);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'database_backup.verification_failed',
            'auditable_id' => $backup->getKey(),
        ]);
        Notification::assertSentOnDemand(BackupFailedNotification::class);
    }

    public function test_retention_removes_expired_artifacts_but_preserves_history(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database/expired.sqlite.gz', 'expired');
        config()->set('operations.backups.retention_days', 14);

        $backup = DatabaseBackup::query()->create([
            'driver' => 'sqlite',
            'connection_name' => 'sqlite',
            'database_name' => 'database.sqlite',
            'status' => DatabaseBackupStatus::Succeeded,
            'verification_status' => BackupVerificationStatus::NotRequested,
            'disk' => 'local',
            'path' => 'backups/database/expired.sqlite.gz',
            'bytes' => 7,
            'checksum' => hash('sha256', 'expired'),
            'started_at' => now()->subDays(20),
            'completed_at' => now()->subDays(20),
        ]);
        $backup->forceFill([
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ])->save();

        $this->assertSame(1, app(BackupRetentionService::class)->prune());

        Storage::disk('local')->assertMissing('backups/database/expired.sqlite.gz');
        $this->assertNotNull($backup->fresh()->storage_deleted_at);
        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'database_backup.pruned',
            'auditable_id' => $backup->getKey(),
        ]);
    }

    public function test_public_filesystem_disk_is_rejected(): void
    {
        config()->set('operations.backups.disk', 'public');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private filesystem disk');

        app(DatabaseBackupManager::class)->plan();
    }

    private function mysqlContext(string $password = ''): BackupContext
    {
        return new BackupContext(
            connectionName: 'mysql',
            connection: [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'scratch-test',
                'username' => 'root',
                'password' => $password,
                'unix_socket' => '',
            ],
            disk: 'local',
            path: 'backups/database',
            verificationDatabase: 'scratch-restore-test',
            timeoutSeconds: 60,
        );
    }
}
