<?php

namespace Database\Factories;

use App\Enums\ReferralConversionStatus;
use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use App\Models\PaymentCheckout;
use App\Models\ReferralConversion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralConversion>
 */
class ReferralConversionFactory extends Factory
{
    protected $model = ReferralConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'affiliate_visit_id' => AffiliateVisit::factory(),
            'user_id' => User::factory(),
            'payment_checkout_id' => PaymentCheckout::factory(),
            'payment_order_id' => null,
            'status' => ReferralConversionStatus::Pending,
            'amount' => 0,
            'commission_amount' => 0,
            'converted_at' => null,
            'metadata' => [],
        ];
    }
}
