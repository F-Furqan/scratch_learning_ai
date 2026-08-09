<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaddleWebhookEventJob;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaddleWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaddleWebhookController extends Controller
{
    public function __construct(
        private readonly PaddleWebhookVerifier $verifier,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('payments.paddle.webhook_secret', '');
        $rawPayload = $request->getContent();

        if (blank($secret)) {
            Log::warning('Paddle webhook rejected because no signing secret is configured.');

            return response()->json(['message' => 'Webhook signing secret is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $isValid = $this->verifier->verify(
            $rawPayload,
            $request->header('Paddle-Signature'),
            $secret,
            (int) config('payments.paddle.webhook_tolerance_seconds', 5),
        );

        if (! $isValid) {
            Log::warning('Paddle webhook rejected because signature verification failed.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid webhook payload.'], Response::HTTP_BAD_REQUEST);
        }

        $eventId = $this->eventId($payload);
        $eventType = is_string($payload['event_type'] ?? null) ? $payload['event_type'] : 'unknown';

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            [
                'provider' => 'paddle',
                'event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'status' => 'accepted',
                'payload' => $payload,
                'queued_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessPaddleWebhookEventJob::dispatch($event->id);
        }

        Log::info('Paddle webhook accepted.', [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'duplicate' => ! $event->wasRecentlyCreated,
        ]);

        return response()->json([
            'data' => [
                'accepted' => true,
                'duplicate' => ! $event->wasRecentlyCreated,
                'event_id' => $event->event_id,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): string
    {
        foreach (['event_id', 'notification_id', 'id'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

        return 'generated-'.Str::uuid()->toString();
    }
}
