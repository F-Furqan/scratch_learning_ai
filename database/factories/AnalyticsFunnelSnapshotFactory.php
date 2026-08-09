<?php

namespace Database\Factories;

use App\Models\AnalyticsFunnelSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsFunnelSnapshot>
 */
class AnalyticsFunnelSnapshotFactory extends Factory
{
    protected $model = AnalyticsFunnelSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $visitors = fake()->numberBetween(100, 1000);
        $conversions = fake()->numberBetween(10, $visitors);

        return [
            'funnel_key' => 'public_to_paid_learning',
            'period_date' => today(),
            'period' => 'daily',
            'stage' => 'landing_page_view',
            'stage_order' => 1,
            'visitors_count' => $visitors,
            'users_count' => fake()->numberBetween(10, $visitors),
            'conversions_count' => $conversions,
            'conversion_rate_basis_points' => (int) round(($conversions / max(1, $visitors)) * 10000),
            'metadata' => [],
        ];
    }
}
