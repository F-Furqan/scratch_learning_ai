<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StorageHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'storage';
    }

    public function run(): HealthCheckResult
    {
        $diskName = (string) (config('operations.health.storage_disk') ?: config('filesystems.default'));
        $disk = Storage::disk($diskName);
        $path = 'healthchecks/probe-'.Str::uuid()->toString();
        $contents = Str::random(32);

        try {
            if (! $disk->put($path, $contents) || ! $disk->exists($path) || $disk->get($path) !== $contents) {
                throw new RuntimeException('The health probe could not write and read the configured storage disk.');
            }
        } finally {
            $disk->delete($path);
        }

        return new HealthCheckResult($this->name(), true, [
            'disk' => $diskName,
            'writable' => true,
        ]);
    }
}
