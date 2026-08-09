<?php

namespace App\Http\Controllers\Public;

use App\Enums\AnalyticsEventType;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Analytics\AnalyticsEventService;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        $this->analytics->trackRequest(AnalyticsEventType::BlogListingView, $request, null, [
            'surface' => 'blog_listing',
        ]);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'category' => (string) $request->query('category', ''),
        ];

        $query = BlogPost::query()
            ->published()
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'activeSocialShareImage.media', 'tags']);

        if (filled($filters['search'])) {
            $query->where(function ($query) use ($filters): void {
                $query
                    ->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhere('excerpt', 'like', '%'.$filters['search'].'%');
            });
        }

        if (filled($filters['category'])) {
            $query->whereHas('category', fn ($query) => $query->where('slug', $filters['category']));
        }

        $posts = $query
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = BlogCategory::query()
            ->whereHas('posts', fn ($query) => $query
                ->where('status', PublishStatus::Published->value)
                ->where(function ($query): void {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                }))
            ->orderBy('name')
            ->get()
            ->map(fn (BlogCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values();

        return Inertia::render('public/blog/Index', [
            'posts' => $this->presenter->paginator($posts, fn (BlogPost $post): array => $this->presenter->blogCard($post)),
            'categories' => $categories,
            'filters' => $filters,
            'seo' => $this->presenter->seo([
                'title' => 'Blog',
                'description' => 'Read field notes, platform updates, and practical learning operations guidance.',
            ], route('public.blog.index'), [
                $this->schema->organization(),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Blog', 'url' => route('public.blog.index')],
                ]),
            ]),
        ]);
    }

    public function show(Request $request, string $post): Response
    {
        $postModel = BlogPost::query()
            ->published()
            ->where('slug', $post)
            ->with([
                'author.bloggerProfile',
                'category',
                'featuredImage',
                'activeSocialShareImage.media',
                'tags',
                'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
            ])
            ->firstOrFail();

        $this->analytics->trackBlogView($request, $postModel);

        $faqPayload = $this->presenter->faqs($postModel->faqs);

        $relatedPosts = BlogPost::query()
            ->published()
            ->whereKeyNot($postModel->getKey())
            ->where('blog_category_id', $postModel->blog_category_id)
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'activeSocialShareImage.media', 'tags'])
            ->latest('published_at')
            ->take(3)
            ->get();

        return Inertia::render('public/blog/Show', [
            'post' => $this->presenter->blogDetail($postModel),
            'relatedPosts' => $relatedPosts->map(fn (BlogPost $post): array => $this->presenter->blogCard($post))->values(),
            'seo' => $this->presenter->seoFor($postModel, route('public.blog.show', $postModel->slug), [
                $this->schema->organization(),
                $this->schema->article($postModel),
                $this->schema->faqPage($faqPayload),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Blog', 'url' => route('public.blog.index')],
                    ['label' => $postModel->title, 'url' => route('public.blog.show', $postModel->slug)],
                ]),
            ]),
        ]);
    }
}
