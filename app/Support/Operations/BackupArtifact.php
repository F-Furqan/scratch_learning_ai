<?php

namespace App\Support\Operations;

use App\Models\DatabaseBackup;

final readonly class BackupArtifact
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?int $bytes = null,
        public ?string $checksum = null,
        public ?string $providerReference = null,
        public array $metadata = [],
    ) {}

    public static function fromRecord(DatabaseBackup $backup): self
    {
        return new self(
            disk: $backup->disk,
            path: $backup->path,
            bytes: $backup->bytes,
            checksum: $backup->checksum,
            providerReference: $backup->provider_reference,
            metadata: $backup->metadata ?? [],
        );
    }
}
