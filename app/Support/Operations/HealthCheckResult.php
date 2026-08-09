<?php

namespace App\Support\Operations;

final readonly class HealthCheckResult
{
    /**
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function __construct(
        public string $name,
        public bool $healthy,
        public array $metadata = [],
    ) {}

    /**
     * @return array{status: string, metadata: array<string, bool|float|int|string|null>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->healthy ? 'healthy' : 'unhealthy',
            'metadata' => $this->metadata,
        ];
    }
}
