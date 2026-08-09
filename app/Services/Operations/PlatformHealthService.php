<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Support\Facades\Log;

class PlatformHealthService
{
    /** @var array<string, HealthCheck> */
    private array $checks = [];

    /**
     * @param  iterable<HealthCheck>  $checks
     */
    public function __construct(iterable $checks)
    {
        foreach ($checks as $check) {
            $this->checks[$check->name()] = $check;
        }
    }

    /**
     * @return array{status: string, checked_at: string, checks: array<string, array{status: string, metadata: array<string, bool|float|int|string|null>}>}
     */
    public function report(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            try {
                $results[$name] = $check->run();
            } catch (\Throwable $exception) {
                Log::channel('monitoring')->error('Platform health check failed.', [
                    'check' => $name,
                    'exception' => $exception::class,
                ]);

                $results[$name] = new HealthCheckResult($name, false, [
                    'failure' => class_basename($exception),
                ]);
            }
        }

        $healthy = collect($results)->every(fn (HealthCheckResult $result): bool => $result->healthy);

        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checked_at' => now()->toISOString(),
            'checks' => collect($results)
                ->map(fn (HealthCheckResult $result): array => $result->toArray())
                ->all(),
        ];
    }
}
