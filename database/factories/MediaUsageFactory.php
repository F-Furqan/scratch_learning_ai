<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaUsage> */
class MediaUsageFactory extends Factory
{
    protected $model = MediaUsage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'mediable_type' => BlogPost::class,
            'mediable_id' => BlogPost::factory(),
            'collection' => 'featured_image',
        ];
    }
}
