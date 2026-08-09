<?php

namespace Database\Factories;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AnalyticsEventType::cases());

        return [
            'user_id' => User::factory(),
            'session_id' => (string) Str::uuid(),
            'visitor_id' => (string) Str::uuid(),
            'event_type' => $type,
            'event_name' => $type->value,
            'eventable_type' => null,
            'eventable_id' => null,
            'course_id' => null,
            'course_lesson_id' => null,
            'blog_post_id' => null,
            'payment_checkout_id' => null,
            'payment_order_id' => null,
            'url' => fake()->url(),
            'referrer_url' => fake()->optional()->url(),
            'source' => 'factory',
            'occurred_at' => now(),
            'metadata' => [],
        ];
    }

    public function type(AnalyticsEventType $type): static
    {
        return $this->state(fn (): array => [
            'event_type' => $type,
            'event_name' => $type->value,
        ]);
    }
}
