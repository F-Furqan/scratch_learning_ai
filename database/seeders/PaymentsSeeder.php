<?php

namespace Database\Seeders;

use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Models\Course;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use Illuminate\Database\Seeder;

class PaymentsSeeder extends Seeder
{
    /**
     * Seed starter Paddle catalog records without environment-specific Paddle IDs.
     */
    public function run(): void
    {
        $course = Course::query()->where('slug', 'industrial-laravel-foundations')->first();

        if ($course) {
            $courseProduct = PaymentProduct::query()->firstOrCreate(
                ['slug' => 'industrial-laravel-foundations-course'],
                [
                    'course_id' => $course->id,
                    'name' => 'Industrial Laravel Foundations Course',
                    'description' => 'Permanent access to the flagship industrial Laravel course.',
                    'type' => PaymentProductType::Course,
                    'status' => PaymentProductStatus::Active,
                    'tax_category' => 'training-services',
                    'metadata' => ['seeded' => true],
                ],
            );

            $this->price($courseProduct, 'Course Lifetime Access', PaymentBillingInterval::OneTime, 14_900);
        }

        $premium = PaymentProduct::query()->firstOrCreate(
            ['slug' => 'premium-library'],
            [
                'name' => 'Premium Library',
                'description' => 'Subscription access to all premium courses and lessons.',
                'type' => PaymentProductType::PremiumLibrary,
                'status' => PaymentProductStatus::Active,
                'tax_category' => 'training-services',
                'metadata' => ['feature' => 'premium_library'],
            ],
        );

        $this->price($premium, 'Premium Monthly', PaymentBillingInterval::Month, 2_900, true);
        $this->price($premium, 'Premium Annual', PaymentBillingInterval::Year, 29_000, true);

        $adFree = PaymentProduct::query()->firstOrCreate(
            ['slug' => 'ad-free-plan'],
            [
                'name' => 'Ad-Free Plan',
                'description' => 'Remove ads from the public learning and reading experience.',
                'type' => PaymentProductType::AdFree,
                'status' => PaymentProductStatus::Active,
                'tax_category' => 'training-services',
                'metadata' => ['feature' => 'ad_free'],
            ],
        );

        $this->price($adFree, 'Ad-Free Monthly', PaymentBillingInterval::Month, 900, true);

        $team = PaymentProduct::query()->firstOrCreate(
            ['slug' => 'business-team-plan'],
            [
                'name' => 'Business Team Plan',
                'description' => 'Seat-based team access for companies and training departments.',
                'type' => PaymentProductType::Team,
                'status' => PaymentProductStatus::Active,
                'tax_category' => 'training-services',
                'metadata' => ['feature' => 'team_seats'],
            ],
        );

        $this->price($team, 'Business Team Monthly Seat', PaymentBillingInterval::Month, 1_900, true, 3, 250);
    }

    private function price(PaymentProduct $product, string $name, PaymentBillingInterval $interval, int $amount, bool $recurring = false, ?int $seatMin = null, ?int $seatMax = null): void
    {
        PaymentPrice::query()->firstOrCreate(
            ['payment_product_id' => $product->id, 'name' => $name],
            [
                'billing_interval' => $interval,
                'is_recurring' => $recurring,
                'currency' => 'USD',
                'amount' => $amount,
                'seat_min' => $seatMin,
                'seat_max' => $seatMax,
                'is_active' => true,
                'metadata' => ['seeded' => true],
            ],
        );
    }
}
