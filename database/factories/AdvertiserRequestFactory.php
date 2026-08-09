<?php

namespace Database\Factories;

use App\Enums\AdvertiserRequestStatus;
use App\Models\AdvertiserRequest;
use App\Models\AdZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvertiserRequest>
 */
class AdvertiserRequestFactory extends Factory
{
    protected $model = AdvertiserRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_ad_zone_id' => AdZone::factory(),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website_url' => fake()->url(),
            'budget_min' => 500,
            'budget_max' => 5000,
            'message' => fake()->paragraph(),
            'status' => AdvertiserRequestStatus::Pending,
            'metadata' => ['factory' => true],
        ];
    }
}
