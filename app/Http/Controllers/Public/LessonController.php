<?php

namespace App\Http\Controllers\Public;

use App\Enums\CommunityContentStatus;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Support\PublicSite\PublicContentPresenter;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function __construct(
        private readonly PublicContentPresenter $presenter,
        private readonly StructuredDataBuilder $schema,
    ) {}

    public function first(): RedirectResponse
    {
        $lesson = CourseLesson::query()
            ->published()
            ->whereHas('course', fn ($query) => $query
                ->where('status', PublishStatus::Published->value)
                ->where(function ($query): void {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                }))
            ->with('course')
            ->orderBy('order_number')
            ->first();

        if (! $lesson || ! $lesson->course) {
            return redirect()->route('public.courses.index');
        }

        return redirect()->route('public.lessons.show', [
            $lesson->course->slug,
            $lesson->slug,
        ]);
    }

    public function show(Request $request, string $course, string $lesson): Response
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

        $lessonModel = CourseLesson::query()
            ->published()
            ->where('course_id', $courseModel->id)
            ->where('slug', $lesson)
            ->with([
                'course',
                'section',
                'dripSchedule',
                'resources' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'quizzes' => fn ($query) => $query->where('is_active', true)->with(['questions' => fn ($query) => $query->orderBy('sort_order')])->orderBy('sort_order'),
                'assignments' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
                'questions' => fn ($query) => $query
                    ->where('status', PublishStatus::Published->value)
                    ->with([
                        'user',
                        'acceptedAnswer.user',
                        'answers' => fn ($query) => $query
                            ->where('status', CommunityContentStatus::Approved->value)
                            ->with('user')
                            ->latest(),
                    ])
                    ->latest(),
            ])
            ->firstOrFail();

        $lessonModel->setRelation('course', $courseModel);

        $faqPayload = $this->presenter->faqs($lessonModel->faqs);

        return Inertia::render('public/lessons/Show', [
            'course' => $this->presenter->courseDetail($courseModel),
            'lesson' => $this->presenter->lessonDetail($lessonModel, $courseModel, $request->user()),
            'seo' => $this->presenter->seoFor($lessonModel, route('public.lessons.show', [$courseModel->slug, $lessonModel->slug]), [
                $this->schema->organization(),
                $this->schema->lesson($lessonModel),
                $this->schema->faqPage($faqPayload),
                $this->schema->breadcrumbs([
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Courses', 'url' => route('public.courses.index')],
                    ['label' => $courseModel->title, 'url' => route('public.courses.show', $courseModel->slug)],
                    ['label' => $lessonModel->title, 'url' => route('public.lessons.show', [$courseModel->slug, $lessonModel->slug])],
                ]),
            ]),
        ]);
    }
}
