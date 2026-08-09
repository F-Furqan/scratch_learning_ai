<?php

namespace App\Services\Operations;

use App\Contracts\Operations\ManagedSnapshotProvider;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use App\Support\Operations\ManagedSnapshot;
use RuntimeException;

class UnsupportedManagedSnapshotProvider implements ManagedSnapshotProvider
{
    public function create(BackupContext $context): ManagedSnapshot
    {
        throw new RuntimeException('No managed database snapshot provider is configured.');
    }

    public function verify(string $reference): BackupVerification
    {
        throw new RuntimeException('No managed database snapshot provider is configured.');
    }

    public function delete(string $reference): void
    {
        throw new RuntimeException('No managed database snapshot provider is configured.');
    }
}
