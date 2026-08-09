<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnalyticsEventType;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseFaq;
use App\Services\Analytics\AnalyticsEventService;
use App\Support\PublicSite\HomeHeroPresenter;
use App\Support\PublicSite\HomePageSectionPresenter;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly HomeHeroPresenter $heroPresenter,
        private readonly HomePageSectionPresenter $sectionPresenter,
        private readonly StructuredDataBuilder $schema,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->analytics->trackRequest(AnalyticsEventType::LandingPageView, $request, null, [
            'surface' => 'home',
        ]);

        $courses = Course::query()
            ->published()
            ->with(['category', 'thumbnail'])
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->latest('published_at')
            ->take(6)
            ->get();

        $posts = BlogPost::query()
            ->published()
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'tags'])
            ->latest('published_at')
            ->take(3)
            ->get();

        $faqs = CourseFaq::query()
            ->published()
            ->whereNull('course_lesson_id')
            ->orderBy('sort_order')
            ->take(5)
            ->get();

        $faqPayload = $this->presenter->faqs($faqs);

        return Inertia::render('public/Home', [
            'site' => $this->presenter->site(),
            'hero' => $this->heroPresenter->payload(),
            'sections' => $this->sectionPresenter->sections(),
            'courses' => $courses->map(fn (Course $course): array => $this->presenter->courseCard($course))->values(),
            'posts' => $posts->map(fn (BlogPost $post): array => $this->presenter->blogCard($post))->values(),
            'faqs' => $faqPayload,
            'seo' => $this->presenter->seo([
                'title' => 'Industrial-friendly learning for modern teams',
                'description' => 'Browse practical courses, lessons, and field-tested articles built for scalable professional learning.',
            ], route('home'), [
                $this->schema->organization(),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                ]),
                $this->schema->faqPage($faqPayload),
            ]),
        ]);
    }
}
