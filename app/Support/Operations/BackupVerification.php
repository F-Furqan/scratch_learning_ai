<?php

namespace App\Support\Operations;

final readonly class BackupVerification
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $verified,
        public array $metadata = [],
    ) {}
}
