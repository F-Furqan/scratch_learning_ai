<?php

namespace Database\Factories;

use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateVisit>
 */
class AffiliateVisitFactory extends Factory
{
    protected $model = AffiliateVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'user_id' => null,
            'visitor_id' => (string) Str::uuid(),
            'landing_url' => fake()->url(),
            'referrer_url' => fake()->url(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'clicked_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
