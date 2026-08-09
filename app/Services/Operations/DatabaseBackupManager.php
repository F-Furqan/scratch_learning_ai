<?php

namespace App\Services\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupManager
{
    /**
     * @return array{disk: string, path: string, bytes: int}
     */
    public function run(): array
    {
        if (! (bool) config('operations.backups.enabled', true)) {
            throw new RuntimeException('Database backups are disabled.');
        }

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ((string) config("database.connections.{$connection}.driver") !== 'sqlite') {
            throw new RuntimeException('The built-in backup command currently supports SQLite. Use the launch runbook for managed database snapshots in production.');
        }

        if ($database === ':memory:' || ! File::exists($database)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $disk = (string) config('operations.backups.disk', 'local');
        $path = trim((string) config('operations.backups.path', 'backups/database'), '/');
        $filename = Carbon::now()->format('Ymd-His').'-database.sqlite';
        $target = $path.'/'.$filename;
        $contents = File::get($database);

        Storage::disk($disk)->put($target, $contents);
        $this->prune($disk, $path);

        return [
            'disk' => $disk,
            'path' => $target,
            'bytes' => strlen($contents),
        ];
    }

    private function prune(string $disk, string $path): void
    {
        $retentionDays = (int) config('operations.backups.retention_days', 14);

        if ($retentionDays <= 0) {
            return;
        }

        $threshold = Carbon::now()->subDays($retentionDays)->timestamp;

        foreach (Storage::disk($disk)->files($path) as $file) {
            if (Storage::disk($disk)->lastModified($file) < $threshold) {
                Storage::disk($disk)->delete($file);
            }
        }
    }
}
