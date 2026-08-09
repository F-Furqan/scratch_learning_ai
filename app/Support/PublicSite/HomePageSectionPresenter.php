<?php

namespace App\Support\PublicSite;

use App\Enums\GrowthStatus;
use App\Enums\HomePageSectionType;
use App\Enums\LearningCatalogStatus;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\HomePageSection;
use App\Models\LeadMagnet;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

class HomePageSectionPresenter
{
    public function __construct(
        private readonly PublicContentPresenter $content,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        /** @var EloquentCollection<int, HomePageSection> $sections */
        $sections = HomePageSection::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $sections
            ->map(fn (HomePageSection $section): array => $this->section($section))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function section(HomePageSection $section): array
    {
        $type = $this->sectionType($section);
        $payload = $this->payload($section);

        return [
            'id' => $section->id,
            'key' => $section->key,
            'type' => $type->value,
            'eyebrow' => $section->eyebrow,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'body' => $section->body,
            'cta_label' => $section->cta_label,
            'cta_url' => $section->cta_url,
            'background' => $section->background ?: 'white',
            'sort_order' => $section->sort_order,
            'payload' => $payload,
            'data' => $this->dataFor($type, $payload),
        ];
    }

    private function sectionType(HomePageSection $section): HomePageSectionType
    {
        $type = $section->getAttribute('type');

        return $type instanceof HomePageSectionType
            ? $type
            : (HomePageSectionType::tryFrom((string) $type) ?? HomePageSectionType::FeaturedCourses);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HomePageSection $section): array
    {
        $payload = $section->getAttribute('payload');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dataFor(HomePageSectionType $type, array $payload): array
    {
        return match ($type) {
            HomePageSectionType::CourseCategories => [
                'categories' => $this->courseCategories($this->limit($payload, 8)),
            ],
            HomePageSectionType::FeaturedCourses => [
                'courses' => $this->featuredCourses($payload),
            ],
            HomePageSectionType::LearningPaths => [
                'paths' => $this->learningPaths($this->limit($payload, 3)),
            ],
            HomePageSectionType::WhyScratchLearning => [
                'cards' => $this->cards($payload, $this->defaultWhyCards()),
            ],
            HomePageSectionType::Testimonials => [
                'items' => $this->cards($payload, $this->defaultTestimonials(), 'items'),
            ],
            HomePageSectionType::LatestBlogs => [
                'posts' => $this->latestPosts($this->limit($payload, 3)),
            ],
            HomePageSectionType::Faq => [
                'faqs' => $this->faqs($this->limit($payload, 5)),
            ],
            HomePageSectionType::NewsletterLeadMagnet => [
                'lead_magnet' => $this->leadMagnet($payload),
            ],
            HomePageSectionType::FinalCta => [
                'metrics' => $this->cards($payload, $this->defaultCtaMetrics(), 'metrics'),
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courseCategories(int $limit): array
    {
        return CourseCategory::query()
            ->where('is_active', true)
            ->withCount(['courses as published_courses_count' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take($limit)
            ->get()
            ->map(fn (CourseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'url' => route('public.courses.index', ['category' => $category->slug]),
                'course_count' => (int) ($category->getAttribute('published_courses_count') ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function featuredCourses(array $payload): array
    {
        $ids = $this->integerList($payload['course_ids'] ?? null);
        $limit = $this->limit($payload, 6);
        $query = Course::query()
            ->published()
            ->with(['category', 'thumbnail'])
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->latest('published_at');

        if ($ids !== []) {
            $courses = $query->whereKey($ids)->get();

            return $courses
                ->sortBy(fn (Course $course): int => array_search($course->id, $ids, true) ?: 0)
                ->map(fn (Course $course): array => $this->content->courseCard($course))
                ->values()
                ->take($limit)
                ->all();
        }

        return $query
            ->take($limit)
            ->get()
            ->map(fn (Course $course): array => $this->content->courseCard($course))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function learningPaths(int $limit): array
    {
        return LearningPath::query()
            ->where('status', LearningCatalogStatus::Active->value)
            ->withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->take($limit)
            ->get()
            ->map(fn (LearningPath $path): array => [
                'id' => $path->id,
                'title' => $path->title,
                'slug' => $path->slug,
                'description' => $path->description,
                'course_count' => (int) ($path->getAttribute('courses_count') ?? 0),
                'url' => route('public.courses.index', ['path' => $path->slug]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestPosts(int $limit): array
    {
        return BlogPost::query()
            ->published()
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'tags'])
            ->latest('published_at')
            ->take($limit)
            ->get()
            ->map(fn (BlogPost $post): array => $this->content->blogCard($post))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function faqs(int $limit): array
    {
        $faqs = CourseFaq::query()
            ->published()
            ->whereNull('course_lesson_id')
            ->orderBy('sort_order')
            ->take($limit)
            ->get();

        return $this->content->faqs($faqs);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function leadMagnet(array $payload): ?array
    {
        $query = LeadMagnet::query()
            ->with('asset')
            ->where('status', GrowthStatus::Active->value);

        $leadMagnetId = (int) ($payload['lead_magnet_id'] ?? 0);

        if ($leadMagnetId > 0) {
            $query->whereKey($leadMagnetId);
        }

        $leadMagnet = $query->latest()->first();

        if (! $leadMagnet) {
            return null;
        }

        return [
            'id' => $leadMagnet->id,
            'title' => $leadMagnet->title,
            'slug' => $leadMagnet->slug,
            'description' => $leadMagnet->description,
            'form_headline' => $leadMagnet->form_headline,
            'delivery_url' => $leadMagnet->delivery_url,
            'asset_url' => $leadMagnet->asset?->url,
            'action_url' => route('growth.lead-magnets.submissions.store', $leadMagnet->slug),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private function cards(array $payload, array $fallback, string $key = 'cards'): array
    {
        $cards = $payload[$key] ?? null;

        if (! is_array($cards) || $cards === []) {
            return $fallback;
        }

        return collect($cards)
            ->filter(fn (mixed $card): bool => is_array($card))
            ->map(fn (mixed $card): array => Arr::only((array) $card, [
                'title',
                'body',
                'name',
                'role',
                'quote',
                'rating',
                'metric',
                'label',
                'icon',
                'url',
            ]))
            ->values()
            ->take(8)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function limit(array $payload, int $default): int
    {
        $limit = (int) ($payload['limit'] ?? $default);

        return min(max($limit, 1), 12);
    }

    /**
     * @return array<int, int>
     */
    private function integerList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultWhyCards(): array
    {
        return [
            ['icon' => 'circle-play', 'title' => 'Continue watching', 'body' => 'Students return to the exact next lesson with progress, notes, bookmarks, and protected resources connected.'],
            ['icon' => 'badge-check', 'title' => 'Editorial trust', 'body' => 'Approval histories, reviewer comments, author badges, and verified experts make public content credible.'],
            ['icon' => 'users', 'title' => 'Teams and seats', 'body' => 'Business learning plans are ready for subscriptions, cohorts, premium access, and team management.'],
            ['icon' => 'chart', 'title' => 'Reporting pro', 'body' => 'Funnels, cohorts, revenue, reconciliation, and author reports help operators improve the platform.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTestimonials(): array
    {
        return [
            ['name' => 'Ayesha Khan', 'role' => 'Learning Operations Lead', 'quote' => 'Scratch Learning gave our team one place for courses, approvals, and measurable progress.', 'rating' => 5],
            ['name' => 'Daniel Reed', 'role' => 'Engineering Manager', 'quote' => 'The course structure feels practical. Lessons, resources, and quizzes are easy to follow.', 'rating' => 5],
            ['name' => 'Maya Thomas', 'role' => 'Instructor', 'quote' => 'The editorial workflow makes it much easier to publish expert content without losing quality.', 'rating' => 5],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultCtaMetrics(): array
    {
        return [
            ['metric' => '6+', 'label' => 'Published courses'],
            ['metric' => '3', 'label' => 'Learning paths'],
            ['metric' => '24/7', 'label' => 'Self-paced access'],
        ];
    }
}
