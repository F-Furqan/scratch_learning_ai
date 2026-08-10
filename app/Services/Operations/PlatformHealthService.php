<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Models\HealthCheckRun;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

            $this->persist($results[$name]);
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

    private function persist(HealthCheckResult $result): void
    {
        try {
            if (! Schema::hasTable('operations_health_check_runs')) {
                return;
            }

            HealthCheckRun::query()->create([
                'check_name' => $result->name,
                'status' => $result->healthy ? 'healthy' : 'unhealthy',
                'metadata' => $result->metadata,
                'checked_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::channel('monitoring')->warning('Unable to persist platform health check result.', [
                'check' => $result->name,
                'exception' => $exception::class,
            ]);
        }
    }
}
