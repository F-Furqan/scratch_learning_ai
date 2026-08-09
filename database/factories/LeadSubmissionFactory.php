<?php

namespace Database\Factories;

use App\Enums\LeadSubmissionStatus;
use App\Models\LeadMagnet;
use App\Models\LeadSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSubmission>
 */
class LeadSubmissionFactory extends Factory
{
    protected $model = LeadSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_magnet_id' => LeadMagnet::factory(),
            'newsletter_campaign_id' => null,
            'user_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => LeadSubmissionStatus::New,
            'source_url' => fake()->url(),
            'metadata' => [],
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }
}
