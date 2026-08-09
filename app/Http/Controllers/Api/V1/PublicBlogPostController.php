<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicBlogPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 12), 1), 50);
        $category = $request->query('category');
        $tag = $request->query('tag');
        $search = $request->query('search');

        $posts = BlogPost::query()
            ->published()
            ->with(['category', 'author', 'featuredImage', 'activeSocialShareImage.media', 'tags'])
            ->when(is_string($category) && filled($category), function ($query) use ($category): void {
                $query->whereHas('category', fn ($query) => $query->where('slug', $category));
            })
            ->when(is_string($tag) && filled($tag), function ($query) use ($tag): void {
                $query->whereHas('tags', fn ($query) => $query->where('slug', $tag));
            })
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->when(is_string($search) && filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return BlogPostResource::collection($posts);
    }

    public function show(BlogPost $post): BlogPostResource
    {
        abort_unless($post->isPublished(), 404);

        $post->load([
            'category',
            'author',
            'featuredImage',
            'activeSocialShareImage.media',
            'tags',
            'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        return BlogPostResource::make($post);
    }
}
