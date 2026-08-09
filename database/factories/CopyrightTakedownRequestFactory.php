<?php

namespace Database\Factories;

use App\Enums\CopyrightTakedownStatus;
use App\Models\CopyrightTakedownRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CopyrightTakedownRequest>
 */
class CopyrightTakedownRequestFactory extends Factory
{
    protected $model = CopyrightTakedownRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reportable_type' => null,
            'reportable_id' => null,
            'status' => CopyrightTakedownStatus::Submitted,
            'claimant_name' => fake()->name(),
            'claimant_email' => fake()->safeEmail(),
            'claimant_company' => fake()->optional()->company(),
            'rights_owner' => fake()->name(),
            'original_work_url' => fake()->optional()->url(),
            'infringing_url' => fake()->url(),
            'content_title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'good_faith_confirmed' => true,
            'accuracy_confirmed' => true,
            'signature' => fake()->name(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Factory Browser',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'resolution_note' => null,
        ];
    }
}
