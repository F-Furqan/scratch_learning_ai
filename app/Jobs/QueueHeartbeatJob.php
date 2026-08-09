<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(): void
    {
        $maximumAge = max(60, (int) config('operations.health.queue_heartbeat_max_age_seconds', 180));

        Cache::put(
            (string) config('operations.health.queue_heartbeat_key'),
            now()->timestamp,
            now()->addSeconds($maximumAge * 3),
        );
    }
}
