<?php

namespace App\Contracts\Operations;

use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;
use App\Support\Operations\ManagedSnapshot;

interface ManagedSnapshotProvider
{
    public function create(BackupContext $context): ManagedSnapshot;

    public function verify(string $reference): BackupVerification;

    public function delete(string $reference): void;
}
