<?php

namespace App\Http\Controllers\Public;

use App\Enums\BloggerStatus;
use App\Http\Controllers\Controller;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\User;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Inertia\Inertia;
use Inertia\Response;

class BloggerProfileController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
    ) {}

    public function index(): Response
    {
        $profiles = BloggerProfile::query()
            ->where('status', BloggerStatus::Approved)
            ->with(['user' => fn ($query) => $query
                ->with(['authorBadges', 'instructorProfile'])
                ->withCount(['blogPosts' => fn ($query) => $query->published()])])
            ->latest('reviewed_at')
            ->paginate(12);

        return Inertia::render('public/bloggers/Index', [
            'bloggers' => $this->presenter->paginator($profiles, fn (BloggerProfile $profile): array => $this->presenter->blogger($profile->user)),
            'seo' => $this->presenter->seo([
                'title' => 'Blogger profiles',
                'description' => 'Meet approved authors sharing practical course and learning operations expertise.',
            ], route('public.bloggers.index'), [
                $this->schema->organization(),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Bloggers', 'url' => route('public.bloggers.index')],
                ]),
            ]),
        ]);
    }

    public function show(int $user): Response
    {
        $profile = BloggerProfile::query()
            ->where('status', BloggerStatus::Approved)
            ->where('user_id', $user)
            ->with(['user' => fn ($query) => $query
                ->with(['authorBadges', 'instructorProfile'])
                ->withCount(['blogPosts' => fn ($query) => $query->published()])])
            ->firstOrFail();

        $posts = BlogPost::query()
            ->published()
            ->where('author_id', $profile->user_id)
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'tags'])
            ->latest('published_at')
            ->paginate(6);

        /** @var User $author */
        $author = $profile->user;

        return Inertia::render('public/bloggers/Show', [
            'blogger' => $this->presenter->blogger($author),
            'posts' => $this->presenter->paginator($posts, fn (BlogPost $post): array => $this->presenter->blogCard($post)),
            'seo' => $this->presenter->seo([
                'title' => $author->name,
                'description' => $profile->bio,
            ], route('public.bloggers.show', $author->id), [
                $this->schema->organization(),
                $this->schema->person($author),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Bloggers', 'url' => route('public.bloggers.index')],
                    ['label' => $author->name, 'url' => route('public.bloggers.show', $author->id)],
                ]),
            ]),
        ]);
    }
}
