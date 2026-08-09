<?php

namespace Database\Factories;

use App\Enums\GrowthStatus;
use App\Models\LeadMagnet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadMagnet>
 */
class LeadMagnetFactory extends Factory
{
    protected $model = LeadMagnet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->word().' '.fake()->unique()->word().' '.fake()->unique()->word().' '.fake()->unique()->word();

        return [
            'asset_media_id' => null,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'description' => fake()->sentence(18),
            'status' => GrowthStatus::Active,
            'form_headline' => 'Get the guide',
            'delivery_url' => fake()->url(),
            'metadata' => [],
        ];
    }
}
