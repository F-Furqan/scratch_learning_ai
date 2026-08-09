<?php

namespace Database\Factories;

use App\Models\AbExperiment;
use App\Models\AbVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbVariant>
 */
class AbVariantFactory extends Factory
{
    protected $model = AbVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->randomElement(['control', 'monthly', 'annual', 'bundle']).fake()->numberBetween(1, 999);

        return [
            'ab_experiment_id' => AbExperiment::factory(),
            'key' => $key,
            'name' => str($key)->replace('-', ' ')->headline()->toString(),
            'weight' => 50,
            'views_count' => 0,
            'conversions_count' => 0,
            'payload' => [
                'cta' => 'Start learning',
            ],
        ];
    }
}
