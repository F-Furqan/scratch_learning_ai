<?php

namespace App\Jobs;

use App\Services\Ads\AdReportAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateAdReportsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    public function handle(AdReportAggregator $aggregator): void
    {
        $aggregator->aggregate(
            $this->from ? Carbon::parse($this->from) : null,
            $this->to ? Carbon::parse($this->to) : null,
        );
    }
}
