<?php

namespace Database\Factories;

use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AbAssignment>
 */
class AbAssignmentFactory extends Factory
{
    protected $model = AbAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ab_experiment_id' => AbExperiment::factory(),
            'ab_variant_id' => AbVariant::factory(),
            'user_id' => null,
            'visitor_id' => (string) Str::uuid(),
            'assigned_at' => now(),
            'converted_at' => null,
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
