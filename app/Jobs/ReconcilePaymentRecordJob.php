<?php

namespace App\Jobs;

use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaddleWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ReconcilePaymentRecordJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $recordId)
    {
        $this->onQueue('payments');
    }

    public function handle(PaddleWebhookProcessor $processor): void
    {
        $record = PaymentReconciliationRecord::query()->findOrFail($this->recordId);
        $event = PaymentWebhookEvent::query()->where('event_id', $record->event_id)->first();

        if (! $event) {
            throw new RuntimeException('The source Paddle webhook event is not available.');
        }

        $event->forceFill([
            'status' => 'queued',
            'processed_at' => null,
            'last_error' => null,
            'queued_at' => now(),
        ])->save();

        $processor->process($event);

        $record->forceFill([
            'status' => 'reconciled',
            'reconciled_at' => now(),
            'notes' => 'Reprocessed from the admin reconciliation action.',
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        PaymentReconciliationRecord::query()
            ->whereKey($this->recordId)
            ->update([
                'status' => 'failed',
                'notes' => $exception->getMessage(),
                'updated_at' => now(),
            ]);
    }
}
