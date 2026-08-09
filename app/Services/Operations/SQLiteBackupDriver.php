<?php

namespace App\Services\Operations;

use App\Contracts\Operations\BackupDriver;
use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

class SQLiteBackupDriver implements BackupDriver
{
    public function __construct(
        private readonly BackupFileStore $files,
    ) {}

    public function name(): string
    {
        return 'sqlite';
    }

    public function create(BackupContext $context): BackupArtifact
    {
        $database = $this->databasePath($context);

        if ($database === ':memory:' || ! File::isFile($database)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $snapshot = $this->files->temporaryPath('.sqlite');
        $compressed = null;

        try {
            if (! File::copy($database, $snapshot)) {
                throw new RuntimeException('Unable to create the SQLite backup snapshot.');
            }

            $compressed = $this->files->compress($snapshot);

            return $this->files->store($context, $compressed, 'sqlite', [
                'compression' => 'gzip',
            ]);
        } finally {
            File::delete(array_filter([$snapshot, $compressed]));
        }
    }

    public function verify(BackupContext $context, BackupArtifact $artifact): BackupVerification
    {
        $compressed = $this->files->materialize($artifact);
        $database = null;

        try {
            $database = $this->files->decompress($compressed);
            $pdo = new PDO('sqlite:'.$database);
            $statement = $pdo->query('PRAGMA integrity_check');

            if ($statement === false) {
                throw new RuntimeException('Unable to run SQLite integrity verification.');
            }

            $result = $statement->fetchColumn();

            if ($result !== 'ok') {
                throw new RuntimeException('SQLite integrity verification failed.');
            }

            return new BackupVerification(true, ['integrity_check' => 'ok']);
        } finally {
            File::delete(array_filter([$compressed, $database]));
        }
    }

    public function delete(BackupArtifact $artifact): void
    {
        $this->files->delete($artifact);
    }

    private function databasePath(BackupContext $context): string
    {
        $database = $context->databaseName();

        if ($database === ':memory:' || str_starts_with($database, DIRECTORY_SEPARATOR)) {
            return $database;
        }

        return database_path($database);
    }
}
