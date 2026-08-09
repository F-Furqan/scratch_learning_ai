<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'database';
    }

    public function run(): HealthCheckResult
    {
        $startedAt = microtime(true);
        $connection = DB::connection();
        $connection->getPdo();

        if ((int) $connection->scalar('select 1') !== 1) {
            throw new RuntimeException('The database readiness query returned an unexpected result.');
        }

        return new HealthCheckResult($this->name(), true, [
            'connection' => (string) config('database.default'),
            'latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
    }
}
