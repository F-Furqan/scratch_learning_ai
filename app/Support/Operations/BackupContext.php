<?php

namespace App\Support\Operations;

final readonly class BackupContext
{
    /**
     * @param  array<string, mixed>  $connection
     */
    public function __construct(
        public string $connectionName,
        public array $connection,
        public string $disk,
        public string $path,
        public string $verificationDatabase,
        public int $timeoutSeconds,
    ) {}

    public function databaseName(): string
    {
        return (string) ($this->connection['database'] ?? '');
    }

    public function databaseDriver(): string
    {
        return (string) ($this->connection['driver'] ?? '');
    }
}
