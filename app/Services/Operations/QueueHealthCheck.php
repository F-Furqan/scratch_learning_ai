<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class QueueHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'queue';
    }

    public function run(): HealthCheckResult
    {
        $connection = (string) config('queue.default');
        $queue = (string) (config("queue.connections.{$connection}.queue") ?: 'default');
        $pendingJobs = Queue::connection($connection)->size($queue);
        $heartbeatRequired = (bool) config('operations.health.queue_heartbeat_required', true);
        $heartbeat = Cache::get((string) config('operations.health.queue_heartbeat_key'));
        $heartbeatAge = is_numeric($heartbeat)
            ? max(0, time() - (int) $heartbeat)
            : null;
        $maximumAge = max(60, (int) config('operations.health.queue_heartbeat_max_age_seconds', 180));
        $healthy = ! $heartbeatRequired || ($heartbeatAge !== null && $heartbeatAge <= $maximumAge);

        return new HealthCheckResult($this->name(), $healthy, [
            'connection' => $connection,
            'queue' => $queue,
            'pending_jobs' => $pendingJobs,
            'heartbeat_required' => $heartbeatRequired,
            'heartbeat_age_seconds' => $heartbeatAge,
        ]);
    }
}
