<?php

namespace App\Services\Operations;

use App\Contracts\Operations\BackupDriver;
use App\Contracts\Operations\ManagedSnapshotProvider;
use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use RuntimeException;

class ManagedSnapshotDriver implements BackupDriver
{
    public function __construct(
        private readonly ManagedSnapshotProvider $provider,
    ) {}

    public function name(): string
    {
        return 'managed';
    }

    public function create(BackupContext $context): BackupArtifact
    {
        $snapshot = $this->provider->create($context);

        return new BackupArtifact(
            bytes: $snapshot->bytes,
            checksum: $snapshot->checksum,
            providerReference: $snapshot->reference,
            metadata: $snapshot->metadata,
        );
    }

    public function verify(BackupContext $context, BackupArtifact $artifact): BackupVerification
    {
        if ($artifact->providerReference === null) {
            throw new RuntimeException('The managed backup has no provider snapshot reference.');
        }

        return $this->provider->verify($artifact->providerReference);
    }

    public function delete(BackupArtifact $artifact): void
    {
        if ($artifact->providerReference !== null) {
            $this->provider->delete($artifact->providerReference);
        }
    }
}
