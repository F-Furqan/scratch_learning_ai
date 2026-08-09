<?php

namespace Database\Factories;

use App\Models\CohortRetentionSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CohortRetentionSnapshot>
 */
class CohortRetentionSnapshotFactory extends Factory
{
    protected $model = CohortRetentionSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $users = fake()->numberBetween(20, 300);
        $retained = fake()->numberBetween(5, $users);

        return [
            'cohort_month' => today()->startOfMonth(),
            'period_number' => fake()->numberBetween(0, 6),
            'users_count' => $users,
            'retained_users_count' => $retained,
            'retention_rate_basis_points' => (int) round(($retained / max(1, $users)) * 10000),
            'metadata' => [],
        ];
    }
}
