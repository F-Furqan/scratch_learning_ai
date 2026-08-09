<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
    ) {}

    public function show(string $page): Response
    {
        $pageModel = Page::query()
            ->published()
            ->where('slug', $page)
            ->with([
                'author.bloggerProfile',
                'blocks' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            ])
            ->firstOrFail();

        return Inertia::render('public/pages/Show', [
            'page' => $this->presenter->page($pageModel),
            'seo' => $this->presenter->seoFor($pageModel, route('public.pages.show', $pageModel->slug), [
                $this->schema->organization(),
                $this->schema->webPage($pageModel),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => $pageModel->title, 'url' => route('public.pages.show', $pageModel->slug)],
                ]),
            ]),
        ]);
    }
}
