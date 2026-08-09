<?php

namespace Database\Factories;

use App\Models\PaymentWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    protected $model = PaymentWebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = (string) $this->faker->uuid();

        return [
            'provider' => 'paddle',
            'event_id' => $eventId,
            'event_type' => 'transaction.completed',
            'status' => 'accepted',
            'payload' => [
                'event_id' => $eventId,
                'event_type' => 'transaction.completed',
                'data' => [],
            ],
            'attempts' => 0,
            'last_error' => null,
            'queued_at' => null,
            'processed_at' => null,
        ];
    }
}
