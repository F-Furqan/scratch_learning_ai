<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicCourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 12), 1), 50);
        $category = $request->query('category');
        $level = $request->query('level');
        $search = $request->query('search');

        $courses = Course::query()
            ->published()
            ->with(['category', 'subcategory', 'thumbnail', 'activeSocialShareImage.media'])
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->when(is_string($category) && filled($category), function ($query) use ($category): void {
                $query->where(function ($query) use ($category): void {
                    $query
                        ->whereHas('category', fn ($query) => $query->where('slug', $category))
                        ->orWhereHas('subcategory', fn ($query) => $query->where('slug', $category));
                });
            })
            ->when(is_string($level) && filled($level), fn ($query) => $query->where('level', $level))
            ->when(is_string($search) && filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%");
                });
            })
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return CourseResource::collection($courses);
    }

    public function show(Course $course): CourseResource
    {
        abort_unless($course->isPublished(), 404);

        $course->load([
            'category',
            'subcategory',
            'thumbnail',
            'activeSocialShareImage.media',
            'sections' => fn ($query) => $query
                ->published()
                ->orderBy('sort_order')
                ->with(['lessons' => fn ($query) => $query->published()->orderBy('order_number')]),
            'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        $course->loadCount(['lessons' => fn ($query) => $query->published()]);

        return CourseResource::make($course);
    }
}
