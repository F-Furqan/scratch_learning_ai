<?php

namespace Database\Factories;

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->unique()->slug().'.jpg';

        return [
            'folder_id' => MediaFolder::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'public',
            'path' => 'media/'.$filename,
            'url' => '/storage/media/'.$filename,
            'title' => fake()->sentence(3),
            'alt_text' => fake()->sentence(5),
            'caption' => fake()->optional()->sentence(),
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'width' => fake()->randomElement([1280, 1600, 1920]),
            'height' => fake()->randomElement([720, 900, 1080]),
            'visibility' => MediaVisibility::Public,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function private(): static
    {
        return $this->state(fn (): array => [
            'visibility' => MediaVisibility::Private,
        ]);
    }
}
