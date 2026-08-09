<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\Concerns\SerializesResourceAttributes;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
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
            'excerpt' => $this->sanitizedText($this->excerpt),
            'content' => $this->when($request->routeIs('api.v1.blogs.show'), $this->sanitizedText($this->content)),
            'status' => $this->enumValue(data_get($this->resource, 'status')),
            'published_at' => $this->isoDate(data_get($this->resource, 'published_at')),
            'is_featured' => (bool) $this->is_featured,
            'seo' => $this->seoPayload(),
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ] : null,
            ),
            'author' => $this->whenLoaded(
                'author',
                fn () => $this->author ? [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                ] : null,
            ),
            'featured_image' => $this->whenLoaded(
                'featuredImage',
                fn () => $this->featuredImage ? MediaAssetResource::make($this->featuredImage) : null,
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
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])->values(),
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
