<?php

namespace App\Services\Operations;

use App\Contracts\Operations\BackupDriver;
use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class MySqlBackupDriver implements BackupDriver
{
    public function __construct(
        private readonly BackupFileStore $files,
    ) {}

    public function name(): string
    {
        return 'mysql';
    }

    public function create(BackupContext $context): BackupArtifact
    {
        $sql = $this->files->temporaryPath('.sql');
        $compressed = null;

        try {
            $command = [
                (string) config('operations.backups.mysql.dump_binary', 'mysqldump'),
                ...$this->connectionArguments($context),
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $context->databaseName(),
            ];

            $this->run($context, $command, 'mysqldump');

            if (! File::isFile($sql)) {
                throw new RuntimeException('mysqldump completed without creating an output file.');
            }

            $compressed = $this->files->compress($sql);

            return $this->files->store($context, $compressed, 'sql', [
                'compression' => 'gzip',
                'transaction_safe' => true,
                'includes_routines' => true,
                'includes_triggers' => true,
                'includes_events' => true,
            ]);
        } finally {
            File::delete(array_filter([$sql, $compressed]));
        }
    }

    public function verify(BackupContext $context, BackupArtifact $artifact): BackupVerification
    {
        $database = $this->validatedVerificationDatabase($context);
        $compressed = $this->files->materialize($artifact);
        $cleanupRequired = false;
        $failure = null;

        try {
            $existing = $this->runMysql($context, [
                '--batch',
                '--skip-column-names',
                "--execute=SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$database}'",
            ], 'check disposable restore database');

            if (trim($existing->output()) !== '') {
                throw new RuntimeException("Disposable restore database [{$database}] already exists; refusing to overwrite it.");
            }

            $cleanupRequired = true;
            $this->runMysql($context, [
                '--execute=CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            ], 'create disposable restore database');

            $input = gzopen($compressed, 'rb');

            if ($input === false) {
                throw new RuntimeException('Unable to open the compressed MySQL backup for restore.');
            }

            try {
                $this->runMysql($context, [$database], 'restore backup', $input);
            } finally {
                gzclose($input);
            }

            $result = $this->runMysql($context, [
                '--batch',
                '--skip-column-names',
                '--execute=SHOW TABLES',
                $database,
            ], 'inspect restored database');

            $tables = array_values(array_filter(preg_split('/\R/', trim($result->output())) ?: []));

            return new BackupVerification(true, [
                'restore_database' => $database,
                'table_count' => count($tables),
            ]);
        } catch (\Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            File::delete($compressed);

            if ($cleanupRequired) {
                try {
                    $this->runMysql($context, [
                        '--execute=DROP DATABASE IF EXISTS `'.$database.'`',
                    ], 'remove disposable restore database');
                } catch (\Throwable $cleanupException) {
                    if ($failure === null) {
                        throw $cleanupException;
                    }
                }
            }
        }
    }

    public function delete(BackupArtifact $artifact): void
    {
        $this->files->delete($artifact);
    }

    /**
     * @param  list<string>  $arguments
     * @param  resource|null  $input
     */
    private function runMysql(BackupContext $context, array $arguments, string $operation, $input = null): ProcessResult
    {
        return $this->run($context, [
            (string) config('operations.backups.mysql.client_binary', 'mysql'),
            ...$this->connectionArguments($context),
            ...$arguments,
        ], $operation, $input);
    }

    /**
     * @param  list<string>  $command
     * @param  resource|null  $input
     */
    private function run(BackupContext $context, array $command, string $operation, $input = null): ProcessResult
    {
        $process = Process::timeout($context->timeoutSeconds)
            ->env($this->processEnvironment($context));

        if (is_resource($input)) {
            $process->input($input);
        }

        $result = $process->run($command);

        if ($result->failed()) {
            $reason = trim($this->sanitize($result->errorOutput(), $context));
            $suffix = $reason === '' ? '' : ': '.$reason;

            throw new RuntimeException("MySQL backup operation [{$operation}] failed with exit code {$result->exitCode()}{$suffix}");
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function connectionArguments(BackupContext $context): array
    {
        $arguments = [
            '--user='.(string) ($context->connection['username'] ?? ''),
        ];

        $socket = (string) ($context->connection['unix_socket'] ?? '');

        if ($socket !== '') {
            $arguments[] = '--socket='.$socket;
        } else {
            $arguments[] = '--host='.(string) ($context->connection['host'] ?? '127.0.0.1');
            $arguments[] = '--port='.(string) ($context->connection['port'] ?? 3306);
        }

        return $arguments;
    }

    /**
     * @return array<string, string>
     */
    private function processEnvironment(BackupContext $context): array
    {
        $password = (string) ($context->connection['password'] ?? '');

        return $password === '' ? [] : ['MYSQL_PWD' => $password];
    }

    private function validatedVerificationDatabase(BackupContext $context): string
    {
        $database = $context->verificationDatabase;

        if ($database === $context->databaseName()
            || preg_match('/\A[a-z0-9_-]*restore[a-z0-9_-]*test\z/i', $database) !== 1) {
            throw new RuntimeException('The disposable restore database name must be different from the source and end in restore-test.');
        }

        return $database;
    }

    private function sanitize(string $message, BackupContext $context): string
    {
        $password = (string) ($context->connection['password'] ?? '');
        $sanitized = $password === '' ? $message : str_replace($password, '[REDACTED]', $message);

        return mb_substr($sanitized, 0, 4000);
    }
}
