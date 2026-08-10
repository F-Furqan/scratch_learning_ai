<?php

namespace App\Services\Operations;

use App\Models\SchedulerHeartbeat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

final class SchedulerHeartbeatRecorder
{
    public function start(string $name): ?SchedulerHeartbeat
    {
        if (! Schema::hasTable('operations_scheduler_heartbeats')) {
            return null;
        }

        return SchedulerHeartbeat::query()->create([
            'name' => $name,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function finish(?SchedulerHeartbeat $heartbeat, string $status, array $metadata = []): void
    {
        if ($heartbeat === null) {
            return;
        }

        $completedAt = now();
        $startedAt = $heartbeat->getAttribute('started_at');
        $duration = $startedAt instanceof CarbonInterface
            ? max(0, (int) $startedAt->diffInMilliseconds($completedAt))
            : null;

        $heartbeat->forceFill([
            'status' => $status,
            'completed_at' => $completedAt,
            'duration_ms' => $duration,
            'metadata' => $metadata,
        ])->save();
    }
}
