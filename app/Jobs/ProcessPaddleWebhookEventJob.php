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

    public function __construct(
        public readonly int $eventId,
    ) {}

    public function handle(PaddleWebhookProcessor $processor): void
    {
        $event = PaymentWebhookEvent::query()->findOrFail($this->eventId);

        $processor->process($event);
    }
}
