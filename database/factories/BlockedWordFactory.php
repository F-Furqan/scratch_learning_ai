<?php

namespace Database\Factories;

use App\Models\BlockedWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedWord>
 */
class BlockedWordFactory extends Factory
{
    protected $model = BlockedWord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word' => fake()->unique()->word(),
            'match_type' => 'contains',
            'severity' => fake()->numberBetween(1, 3),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
