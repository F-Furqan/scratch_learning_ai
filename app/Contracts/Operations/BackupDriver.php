<?php

namespace App\Contracts\Operations;

use App\Support\Operations\BackupArtifact;
use App\Support\Operations\BackupContext;
use App\Support\Operations\BackupVerification;

interface BackupDriver
{
    public function name(): string;

    public function create(BackupContext $context): BackupArtifact;

    public function verify(BackupContext $context, BackupArtifact $artifact): BackupVerification;

    public function delete(BackupArtifact $artifact): void;
}
