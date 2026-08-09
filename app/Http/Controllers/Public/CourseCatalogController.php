<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnalyticsEventType;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Services\Analytics\AnalyticsEventService;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        $this->analytics->trackRequest(AnalyticsEventType::CourseListingView, $request, null, [
            'surface' => 'course_listing',
        ]);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'category' => (string) $request->query('category', ''),
            'level' => (string) $request->query('level', ''),
        ];

        $query = Course::query()
            ->published()
            ->with(['category', 'thumbnail', 'activeSocialShareImage.media'])
            ->withCount(['lessons' => fn ($query) => $query->published()]);

        if (filled($filters['search'])) {
            $query->where(function ($query) use ($filters): void {
                $query
                    ->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhere('short_description', 'like', '%'.$filters['search'].'%');
            });
        }

        if (filled($filters['category'])) {
            $query->whereHas('category', fn ($query) => $query->where('slug', $filters['category']));
        }

        if (filled($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        $courses = $query
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = CourseCategory::query()
            ->whereHas('courses', fn ($query) => $query
                ->where('status', PublishStatus::Published->value)
                ->where(function ($query): void {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                }))
            ->orderBy('name')
            ->get()
            ->map(fn (CourseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values();

        return Inertia::render('public/courses/Index', [
            'courses' => $this->presenter->paginator($courses, fn (Course $course): array => $this->presenter->courseCard($course)),
            'categories' => $categories,
            'filters' => $filters,
            'levels' => ['beginner', 'intermediate', 'advanced'],
            'seo' => $this->presenter->seo([
                'title' => 'Courses',
                'description' => 'Explore practical courses designed for industrial-grade learning operations.',
            ], route('public.courses.index'), [
                $this->schema->organization(),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Courses', 'url' => route('public.courses.index')],
                ]),
            ]),
        ]);
    }

    public function show(Request $request, string $course): Response
    {
        $courseModel = Course::query()
            ->published()
            ->where('slug', $course)
            ->with([
                'category',
                'subcategory',
                'creator.bloggerProfile',
                'thumbnail',
                'activeSocialShareImage.media',
                'paymentProduct.prices' => fn ($query) => $query->where('is_active', true)->orderBy('amount'),
                'sections' => fn ($query) => $query->published()->orderBy('sort_order'),
                'sections.lessons' => fn ($query) => $query->published()->orderBy('order_number'),
                'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
            ])
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->firstOrFail();

        $this->analytics->trackCourseView($request, $courseModel);

        $faqPayload = $this->presenter->faqs($courseModel->faqs);

        $relatedCourses = Course::query()
            ->published()
            ->whereKeyNot($courseModel->getKey())
            ->where('course_category_id', $courseModel->course_category_id)
            ->with(['category', 'thumbnail', 'activeSocialShareImage.media'])
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->latest('published_at')
            ->take(3)
            ->get();

        return Inertia::render('public/courses/Show', [
            'course' => $this->presenter->courseDetail($courseModel),
            'relatedCourses' => $relatedCourses->map(fn (Course $course): array => $this->presenter->courseCard($course))->values(),
            'seo' => $this->presenter->seoFor($courseModel, route('public.courses.show', $courseModel->slug), [
                $this->schema->organization(),
                $this->schema->course($courseModel),
                $this->schema->faqPage($faqPayload),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Courses', 'url' => route('public.courses.index')],
                    ['label' => $courseModel->title, 'url' => route('public.courses.show', $courseModel->slug)],
                ]),
            ]),
        ]);
    }
}
