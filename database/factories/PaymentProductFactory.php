<?php

namespace Database\Factories;

use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\PaymentProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProduct>
 */
class PaymentProductFactory extends Factory
{
    protected $model = PaymentProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => null,
            'course_bundle_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(16),
            'type' => PaymentProductType::Subscription,
            'status' => PaymentProductStatus::Active,
            'paddle_product_id' => 'pro_'.strtolower(fake()->bothify('??????????????????????????')),
            'tax_category' => 'training-services',
            'metadata' => [],
        ];
    }

    public function course(?Course $course = null): static
    {
        return $this->state(fn (): array => [
            'course_id' => ($course ?? Course::factory()->published()->create())->id,
            'type' => PaymentProductType::Course,
        ]);
    }

    public function premiumLibrary(): static
    {
        return $this->state(fn (): array => [
            'type' => PaymentProductType::PremiumLibrary,
            'name' => 'Premium Library',
        ]);
    }

    public function team(): static
    {
        return $this->state(fn (): array => [
            'type' => PaymentProductType::Team,
            'name' => 'Team Plan',
        ]);
    }

    public function adFree(): static
    {
        return $this->state(fn (): array => [
            'type' => PaymentProductType::AdFree,
            'name' => 'Ad-Free Plan',
        ]);
    }

    public function bundle(?CourseBundle $bundle = null): static
    {
        return $this->state(fn (): array => [
            'course_bundle_id' => ($bundle ?? CourseBundle::factory()->create())->id,
            'type' => PaymentProductType::Bundle,
            'name' => 'Premium Course Bundle',
        ]);
    }
}
