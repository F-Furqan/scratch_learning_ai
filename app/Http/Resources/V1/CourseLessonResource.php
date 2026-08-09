<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\Concerns\SerializesResourceAttributes;
use App\Models\CourseLesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseLesson
 */
class CourseLessonResource extends JsonResource
{
    use SerializesResourceAttributes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->course_section_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'order_number' => $this->order_number,
            'content' => $this->when(
                $request->routeIs('api.v1.courses.lessons.show'),
                fn () => $this->publicContent(),
            ),
            'preview' => $this->preview(),
            'video_type' => $this->enumValue(data_get($this->resource, 'video_type')),
            'video_url' => $this->video_url,
            'video_file' => $this->whenLoaded(
                'videoFile',
                fn () => $this->videoFile ? MediaAssetResource::make($this->videoFile) : null,
            ),
            'is_free' => (bool) $this->is_free,
            'is_paid' => (bool) $this->is_paid,
            'preview_word_limit' => $this->preview_word_limit,
            'allow_comments' => (bool) $this->allow_comments,
            'allow_questions' => (bool) $this->allow_questions,
            'status' => $this->enumValue(data_get($this->resource, 'status')),
            'published_at' => $this->isoDate(data_get($this->resource, 'published_at')),
            'seo' => $this->seoPayload(),
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

    private function publicContent(): ?string
    {
        if ($this->is_free || ! $this->is_paid) {
            return $this->sanitizedText($this->content);
        }

        return null;
    }

    private function preview(): ?string
    {
        if (blank($this->content) || blank($this->preview_word_limit)) {
            return null;
        }

        return $this->sanitizedPreview($this->content, (int) $this->preview_word_limit);
    }
}
