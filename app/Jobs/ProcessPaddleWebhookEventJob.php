<?php

namespace App\Jobs;

use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaddleWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaddleWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public readonly int $eventId,
    ) {
        $this->onQueue('payments');
    }

    public function handle(PaddleWebhookProcessor $processor): void
    {
        $event = PaymentWebhookEvent::query()->findOrFail($this->eventId);

        $processor->process($event);
    }
}
