<?php

namespace App\Support\Operations;

final readonly class ManagedSnapshot
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $reference,
        public ?int $bytes = null,
        public ?string $checksum = null,
        public array $metadata = [],
    ) {}
}
