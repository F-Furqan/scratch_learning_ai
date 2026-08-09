<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\Concerns\SerializesResourceAttributes;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    use SerializesResourceAttributes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->sanitizedText($this->short_description),
            'description' => $this->when($request->routeIs('api.v1.courses.show'), $this->sanitizedText($this->description)),
            'intro_video_url' => $this->intro_video_url,
            'level' => $this->level,
            'language' => $this->language,
            'price' => (float) $this->price,
            'is_free' => (bool) $this->is_free,
            'status' => $this->enumValue(data_get($this->resource, 'status')),
            'published_at' => $this->isoDate(data_get($this->resource, 'published_at')),
            'lesson_count' => $this->whenCounted('lessons'),
            'seo' => $this->seoPayload(),
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ] : null,
            ),
            'subcategory' => $this->whenLoaded(
                'subcategory',
                fn () => $this->subcategory ? [
                    'id' => $this->subcategory->id,
                    'name' => $this->subcategory->name,
                    'slug' => $this->subcategory->slug,
                ] : null,
            ),
            'thumbnail' => $this->whenLoaded(
                'thumbnail',
                fn () => $this->thumbnail ? MediaAssetResource::make($this->thumbnail) : null,
            ),
            'social_share_image' => $this->whenLoaded(
                'activeSocialShareImage',
                fn () => $this->activeSocialShareImage ? [
                    'id' => $this->activeSocialShareImage->id,
                    'url' => $this->activeSocialShareImage->image_url ?: $this->activeSocialShareImage->media?->url,
                    'title' => $this->activeSocialShareImage->title,
                    'alt_text' => $this->activeSocialShareImage->alt_text,
                    'template' => $this->activeSocialShareImage->template,
                ] : null,
            ),
            'sections' => $this->whenLoaded(
                'sections',
                fn () => $this->sections->map(fn ($section): array => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'slug' => $section->slug,
                    'description' => $this->sanitizedText($section->description),
                    'sort_order' => $section->sort_order,
                    'lessons' => CourseLessonResource::collection($section->lessons),
                ])->values()->all(),
            ),
            'faqs' => $this->whenLoaded(
                'faqs',
                fn () => $this->faqs->map(fn ($faq): array => [
                    'id' => $faq->id,
                    'question' => $this->sanitizedText($faq->question),
                    'answer' => $this->sanitizedText($faq->answer),
                    'sort_order' => $faq->sort_order,
                ])->values()->all(),
            ),
        ];
    }
}
