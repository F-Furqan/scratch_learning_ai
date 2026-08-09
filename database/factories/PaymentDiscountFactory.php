<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\GrowthStatus;
use App\Models\Course;
use App\Models\PaymentDiscount;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentDiscount>
 */
class PaymentDiscountFactory extends Factory
{
    protected $model = PaymentDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_product_id' => null,
            'payment_price_id' => null,
            'course_id' => null,
            'name' => fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'description' => fake()->sentence(),
            'type' => DiscountType::Percent,
            'value' => fake()->numberBetween(10, 40),
            'currency' => 'USD',
            'status' => GrowthStatus::Active,
            'is_launch_offer' => false,
            'paddle_discount_id' => 'dsc_'.strtolower(fake()->bothify('??????????????????????????')),
            'max_redemptions' => 500,
            'redemptions_count' => 0,
            'per_user_limit' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'metadata' => [],
        ];
    }

    public function forProduct(PaymentProduct $product): static
    {
        return $this->state(fn (): array => [
            'payment_product_id' => $product->id,
        ]);
    }

    public function forPrice(PaymentPrice $price): static
    {
        return $this->state(fn (): array => [
            'payment_product_id' => $price->payment_product_id,
            'payment_price_id' => $price->id,
        ]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'course_id' => $course->id,
        ]);
    }

    public function fixed(int $amount, string $currency = 'USD'): static
    {
        return $this->state(fn (): array => [
            'type' => DiscountType::Fixed,
            'value' => $amount,
            'currency' => strtoupper($currency),
        ]);
    }

    public function launchOffer(): static
    {
        return $this->state(fn (): array => [
            'is_launch_offer' => true,
            'name' => 'Launch Offer',
            'code' => 'LAUNCH'.fake()->unique()->numberBetween(10, 99),
        ]);
    }
}
