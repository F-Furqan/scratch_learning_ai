<?php

namespace Database\Factories;

use App\Enums\CoursePurchaseStatus;
use App\Models\Course;
use App\Models\CoursePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoursePurchase>
 */
class CoursePurchaseFactory extends Factory
{
    protected $model = CoursePurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory()->published(),
            'payment_order_id' => null,
            'source' => 'paddle',
            'status' => CoursePurchaseStatus::Active,
            'purchased_at' => now(),
            'expires_at' => null,
        ];
    }
}
