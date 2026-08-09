<?php

namespace Database\Factories;

use App\Enums\GrowthStatus;
use App\Models\LeadMagnet;
use App\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaign>
 */
class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->unique()->word().' '.fake()->unique()->word();

        return [
            'lead_magnet_id' => LeadMagnet::factory(),
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'subject' => fake()->sentence(6),
            'audience' => 'all_leads',
            'status' => GrowthStatus::Draft,
            'scheduled_at' => null,
            'sent_at' => null,
            'metadata' => [],
        ];
    }
}
