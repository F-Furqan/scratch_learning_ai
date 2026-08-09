<?php

namespace Database\Factories;

use App\Models\MediaFolder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    protected $model = MediaFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'parent_id' => null,
            'name' => Str::headline($name),
            'path' => Str::slug($name),
        ];
    }
}
