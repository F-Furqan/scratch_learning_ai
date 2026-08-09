<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class StructuredDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) config('app.name', 'Scratch Learning'),
            'url' => url('/'),
            'logo' => url('/apple-touch-icon.png'),
        ];
    }

    /**
     * @param  array<int, array{label: string, url?: string|null}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static function (array $item, int $index): array {
                    $node = [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $item['label'],
                    ];

                    if (filled($item['url'] ?? null)) {
                        $node['item'] = $item['url'];
                    }

                    return $node;
                },
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function course(Course $course): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $course->title,
            'description' => $this->plain($course->short_description ?: $course->description),
            'url' => route('public.courses.show', $course->slug),
            'provider' => $this->organizationFragment(),
            'inLanguage' => $course->language,
            'educationalLevel' => $course->level,
            'isAccessibleForFree' => (bool) $course->is_free,
        ];

        if ($course->thumbnail?->url) {
            $data['image'] = $this->absoluteUrl($course->thumbnail->url);
        }

        $publishedAt = $this->dateString($course->getAttribute('published_at'));

        if ($publishedAt) {
            $data['datePublished'] = $publishedAt;
        }

        if (! $course->is_free) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => (float) $course->price,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => route('public.courses.show', $course->slug),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function lesson(CourseLesson $lesson): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LearningResource',
            'name' => $lesson->title,
            'description' => $this->plain($lesson->seoPayload()['description'] ?? $lesson->content),
            'url' => route('public.lessons.show', [$lesson->course?->slug, $lesson->slug]),
            'isAccessibleForFree' => (bool) $lesson->is_free,
            'learningResourceType' => 'Lesson',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function article(BlogPost $post): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $this->plain($post->excerpt ?: $post->content),
            'url' => route('public.blog.show', $post->slug),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?: (string) config('app.name', 'Scratch Learning'),
            ],
            'publisher' => $this->organizationFragment(),
        ];

        if ($post->featuredImage?->url) {
            $data['image'] = $this->absoluteUrl($post->featuredImage->url);
        }

        $publishedAt = $this->atomString($post->getAttribute('published_at'));

        if ($publishedAt) {
            $data['datePublished'] = $publishedAt;
        }

        $updatedAt = $this->atomString($post->getAttribute('updated_at'));

        if ($updatedAt) {
            $data['dateModified'] = $updatedAt;
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @return array<string, mixed>|null
     */
    public function faqPage(array $faqs): ?array
    {
        if ($faqs === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq): array => [
                '@type' => 'Question',
                'name' => (string) $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => (string) $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function webPage(Page $page): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'description' => $this->plain($page->excerpt ?: $page->content),
            'url' => route('public.pages.show', $page->slug),
            'dateModified' => $this->atomString($page->getAttribute('updated_at')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function person(User $user): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->name,
            'url' => route('public.bloggers.show', $user->id),
            'description' => $this->plain($user->bloggerProfile?->bio),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function organizationFragment(): array
    {
        return [
            '@type' => 'Organization',
            'name' => (string) config('app.name', 'Scratch Learning'),
            'sameAs' => url('/'),
        ];
    }

    private function plain(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Str::limit(trim(strip_tags($value)), 220);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return is_string($value) ? $value : null;
    }

    private function atomString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toAtomString();
        }

        return is_string($value) ? $value : null;
    }

    private function absoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
