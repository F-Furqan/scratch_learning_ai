<?php

namespace Database\Factories;

use App\Enums\GrowthStatus;
use App\Models\Course;
use App\Models\SocialShareImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialShareImage>
 */
class SocialShareImageFactory extends Factory
{
    protected $model = SocialShareImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $course = Course::factory()->published()->create();

        return [
            'shareable_type' => $course->getMorphClass(),
            'shareable_id' => $course->id,
            'media_asset_id' => null,
            'image_url' => fake()->imageUrl(1200, 630),
            'title' => fake()->sentence(4),
            'alt_text' => fake()->sentence(6),
            'template' => 'default',
            'status' => GrowthStatus::Active,
            'metadata' => [],
        ];
    }
}
