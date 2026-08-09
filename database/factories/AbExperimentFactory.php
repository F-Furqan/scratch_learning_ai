<?php

namespace Database\Factories;

use App\Enums\GrowthStatus;
use App\Models\AbExperiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbExperiment>
 */
class AbExperimentFactory extends Factory
{
    protected $model = AbExperiment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(3);

        return [
            'key' => $key,
            'name' => str($key)->replace('-', ' ')->headline()->toString(),
            'surface' => 'pricing_cta',
            'status' => GrowthStatus::Active,
            'winning_variant_key' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'metadata' => [],
        ];
    }
}
