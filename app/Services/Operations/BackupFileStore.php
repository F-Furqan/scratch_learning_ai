<?php

namespace App\Services\Operations;

use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BackupFileStore
{
    public function temporaryPath(string $suffix = ''): string
    {
        $directory = storage_path('app/backup-temp');
        File::ensureDirectoryExists($directory, 0700);

        $path = tempnam($directory, 'backup-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary backup storage.');
        }

        chmod($path, 0600);

        if ($suffix === '') {
            return $path;
        }

        $suffixedPath = $path.$suffix;

        if (! File::move($path, $suffixedPath)) {
            File::delete($path);

            throw new RuntimeException('Unable to prepare a temporary backup file.');
        }

        return $suffixedPath;
    }

    public function compress(string $source): string
    {
        $target = $this->temporaryPath('.gz');
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb9');

        if ($input === false || $output === false) {
            is_resource($input) && fclose($input);
            is_resource($output) && gzclose($output);
            File::delete($target);

            throw new RuntimeException('Unable to open temporary files for backup compression.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);

                if ($chunk === false || gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Backup compression failed.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(BackupContext $context, string $compressedFile, string $extension, array $metadata = []): BackupArtifact
    {
        $database = Str::slug($context->databaseName()) ?: 'database';
        $filename = now()->format('Ymd-His').'-'.$database.'-'.Str::lower(Str::random(8)).'.'.$extension.'.gz';
        $path = trim($context->path, '/').'/'.$filename;
        $stream = fopen($compressedFile, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the compressed backup artifact.');
        }

        try {
            $stored = Storage::disk($context->disk)->put($path, $stream, ['visibility' => 'private']);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException("Unable to write the database backup to disk [{$context->disk}].");
        }

        $checksum = hash_file('sha256', $compressedFile);

        if ($checksum === false) {
            Storage::disk($context->disk)->delete($path);

            throw new RuntimeException('Unable to generate the database backup checksum.');
        }

        return new BackupArtifact(
            disk: $context->disk,
            path: $path,
            bytes: (int) Storage::disk($context->disk)->size($path),
            checksum: $checksum,
            metadata: $metadata,
        );
    }

    public function materialize(BackupArtifact $artifact): string
    {
        if ($artifact->disk === null || $artifact->path === null) {
            throw new RuntimeException('The backup artifact does not reference a stored file.');
        }

        $source = Storage::disk($artifact->disk)->readStream($artifact->path);
        $target = $this->temporaryPath('.gz');
        $output = fopen($target, 'wb');

        if ($source === null || $output === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            is_resource($output) && fclose($output);
            File::delete($target);

            throw new RuntimeException('Unable to materialize the backup for verification.');
        }

        try {
            if (stream_copy_to_stream($source, $output) === false) {
                throw new RuntimeException('Unable to copy the backup for verification.');
            }
        } finally {
            fclose($source);
            fclose($output);
        }

        $checksum = hash_file('sha256', $target);

        if ($artifact->checksum !== null && ! hash_equals($artifact->checksum, (string) $checksum)) {
            File::delete($target);

            throw new RuntimeException('Backup checksum verification failed before restore.');
        }

        return $target;
    }

    public function decompress(string $source): string
    {
        $target = $this->temporaryPath();
        $input = gzopen($source, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            is_resource($input) && gzclose($input);
            is_resource($output) && fclose($output);
            File::delete($target);

            throw new RuntimeException('Unable to open the backup for decompression.');
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);

                if ($chunk === false || fwrite($output, $chunk) === false) {
                    throw new RuntimeException('Backup decompression failed.');
                }
            }
        } finally {
            gzclose($input);
            fclose($output);
        }

        return $target;
    }

    public function delete(BackupArtifact $artifact): void
    {
        if ($artifact->disk === null || $artifact->path === null) {
            return;
        }

        if (Storage::disk($artifact->disk)->exists($artifact->path)
            && ! Storage::disk($artifact->disk)->delete($artifact->path)) {
            throw new RuntimeException("Unable to prune backup [{$artifact->path}].");
        }
    }
}
