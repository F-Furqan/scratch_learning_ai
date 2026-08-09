<?php

namespace App\Http\Controllers\Public;

use App\Enums\InstructorProfileStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Inertia\Inertia;
use Inertia\Response;

class InstructorProfileController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
    ) {}

    public function index(): Response
    {
        $profiles = InstructorProfile::query()
            ->where('status', InstructorProfileStatus::Approved)
            ->with(['avatar'])
            ->with(['user' => fn ($query) => $query
                ->with('authorBadges')
                ->withCount([
                    'courses' => fn ($query) => $query->published(),
                    'blogPosts' => fn ($query) => $query->published(),
                ])])
            ->latest('reviewed_at')
            ->paginate(12);

        return Inertia::render('public/instructors/Index', [
            'instructors' => $this->presenter->paginator($profiles, fn (InstructorProfile $profile): array => $this->presenter->instructor($profile)),
            'seo' => $this->presenter->seo([
                'title' => 'Instructors',
                'description' => 'Meet verified instructors building practical courses and editorial learning programs.',
            ], route('public.instructors.index'), [
                $this->schema->organization(),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Instructors', 'url' => route('public.instructors.index')],
                ]),
            ]),
        ]);
    }

    public function show(string $instructor): Response
    {
        $profile = InstructorProfile::query()
            ->where('status', InstructorProfileStatus::Approved)
            ->where('slug', $instructor)
            ->with(['avatar'])
            ->with(['user' => fn ($query) => $query
                ->with('authorBadges')
                ->withCount([
                    'courses' => fn ($query) => $query->published(),
                    'blogPosts' => fn ($query) => $query->published(),
                ])])
            ->firstOrFail();

        $courses = Course::query()
            ->published()
            ->where('created_by', $profile->user_id)
            ->with(['category', 'thumbnail'])
            ->withCount('lessons')
            ->latest('published_at')
            ->paginate(6);
        $posts = BlogPost::query()
            ->published()
            ->where('author_id', $profile->user_id)
            ->with(['author.bloggerProfile', 'category', 'featuredImage', 'tags'])
            ->latest('published_at')
            ->paginate(6, ['*'], 'posts_page');

        return Inertia::render('public/instructors/Show', [
            'instructor' => $this->presenter->instructor($profile),
            'courses' => $this->presenter->paginator($courses, fn (Course $course): array => $this->presenter->courseCard($course)),
            'posts' => $this->presenter->paginator($posts, fn (BlogPost $post): array => $this->presenter->blogCard($post)),
            'seo' => $this->presenter->seo([
                'title' => $profile->display_name,
                'description' => $profile->headline ?: $profile->bio,
            ], route('public.instructors.show', $profile->slug), [
                $this->schema->organization(),
                $profile->user ? $this->schema->person($profile->user) : null,
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Instructors', 'url' => route('public.instructors.index')],
                    ['label' => $profile->display_name, 'url' => route('public.instructors.show', $profile->slug)],
                ]),
            ]),
        ]);
    }
}
