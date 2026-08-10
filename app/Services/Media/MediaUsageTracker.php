<?php

namespace App\Services\Media;

use App\Models\AdCreative;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\HomeHeroSlide;
use App\Models\InstructorProfile;
use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class MediaUsageTracker
{
    public function syncKnown(Model $model): void
    {
        $collections = match (true) {
            $model instanceof Course => [
                'thumbnail' => $model->thumbnail_media_id,
                'ownership_video' => $model->ownership_video_media_id,
            ],
            $model instanceof CourseLesson => ['video' => $model->video_file_id],
            $model instanceof CourseResource => ['resource' => $model->media_asset_id],
            $model instanceof BlogPost => ['featured_image' => $model->featured_image_media_id],
            $model instanceof HomeHeroSlide => ['hero_slide' => $model->media_asset_id],
            $model instanceof InstructorProfile => ['avatar' => $model->avatar_media_id],
            $model instanceof AdCreative => ['creative' => $model->media_asset_id],
            default => [],
        };

        if ($collections === []) {
            return;
        }

        DB::transaction(function () use ($model, $collections): void {
            MediaUsage::query()
                ->where('mediable_type', $model->getMorphClass())
                ->where('mediable_id', $model->getKey())
                ->whereIn('collection', array_keys($collections))
                ->delete();

            foreach ($collections as $collection => $assetId) {
                if (! $assetId) {
                    continue;
                }

                MediaUsage::query()->create([
                    'media_asset_id' => $assetId,
                    'mediable_type' => $model->getMorphClass(),
                    'mediable_id' => $model->getKey(),
                    'collection' => $collection,
                ]);
            }
        });
    }

    public function clear(Model $model): void
    {
        MediaUsage::query()
            ->where('mediable_type', $model->getMorphClass())
            ->where('mediable_id', $model->getKey())
            ->delete();
    }
}
