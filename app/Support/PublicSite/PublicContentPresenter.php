<?php

namespace App\Support\PublicSite;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuestion;
use App\Models\CourseResource;
use App\Models\InstructorProfile;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PaymentPrice;
use App\Models\QuizQuestion;
use App\Models\SocialShareImage;
use App\Models\User;
use App\Services\Growth\GrowthOfferService;
use App\Services\Learning\LearningAccessService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicContentPresenter
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly LearningAccessService $access,
        private readonly GrowthOfferService $offers,
    ) {}

    /**
     * @return array<string, string>
     */
    public function site(): array
    {
        return [
            'name' => (string) config('app.name', 'Scratch Learning'),
            'url' => url('/'),
            'description' => 'Industrial-friendly training, blogs, and operational learning content for modern teams.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>|null>  $structuredData
     * @return array<string, mixed>
     */
    public function seo(array $payload, string $canonicalUrl, array $structuredData = []): array
    {
        $title = $this->stringValue($payload['title'] ?? null) ?: $this->site()['name'];
        $description = $this->stringValue($payload['description'] ?? null) ?: $this->site()['description'];
        $canonical = $this->stringValue($payload['canonical_url'] ?? null) ?: $canonicalUrl;
        $image = $this->stringValue($payload['image'] ?? null);
        $openGraph = is_array($payload['open_graph'] ?? null) ? $payload['open_graph'] : [];
        $twitter = is_array($payload['twitter'] ?? null) ? $payload['twitter'] : [];

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $this->absoluteUrl($canonical),
            'image' => $image ? $this->absoluteUrl($image) : null,
            'open_graph' => [
                'title' => $this->stringValue($openGraph['title'] ?? null) ?: $title,
                'description' => $this->stringValue($openGraph['description'] ?? null) ?: $description,
                'image' => $this->stringValue($openGraph['image'] ?? null) ?: $image,
            ],
            'twitter' => [
                'title' => $this->stringValue($twitter['title'] ?? null) ?: $title,
                'description' => $this->stringValue($twitter['description'] ?? null) ?: $description,
                'image' => $this->stringValue($twitter['image'] ?? null) ?: $image,
            ],
            'structured_data' => array_values(array_filter($structuredData)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $structuredData
     * @return array<string, mixed>
     */
    public function seoFor(Course|CourseLesson|BlogPost|Page $model, string $canonicalUrl, array $structuredData = []): array
    {
        $payload = $model->seoPayload();
        $shareImage = $this->socialShareImage($model);

        if ($shareImage && filled($shareImage['url'] ?? null)) {
            $payload['image'] = $shareImage['url'];
            $payload['open_graph'] = array_replace(is_array($payload['open_graph'] ?? null) ? $payload['open_graph'] : [], [
                'image' => $shareImage['url'],
            ]);
            $payload['twitter'] = array_replace(is_array($payload['twitter'] ?? null) ? $payload['twitter'] : [], [
                'image' => $shareImage['url'],
            ]);
        }

        return $this->seo($payload, $canonicalUrl, $structuredData);
    }

    /**
     * @return array<string, mixed>
     */
    public function courseCard(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'url' => route('public.courses.show', $course->slug),
            'short_description' => $this->sanitizer->plainText($course->short_description),
            'level' => $course->level,
            'language' => $course->language,
            'price' => (float) $course->price,
            'formatted_price' => $this->formatPrice((float) $course->price, (bool) $course->is_free),
            'is_free' => (bool) $course->is_free,
            'lesson_count' => (int) ($course->getAttribute('lessons_count') ?? 0),
            'category' => $course->category ? $this->taxonomy($course->category) : null,
            'thumbnail' => $this->media($course->thumbnail),
            'social_share_image' => $this->socialShareImage($course),
            'published_at' => $this->isoDate($course->getAttribute('published_at')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function courseDetail(Course $course): array
    {
        return [
            ...$this->courseCard($course),
            'description' => $this->sanitizer->plainText($course->description),
            'intro_video_url' => $course->intro_video_url,
            'subcategory' => $course->subcategory ? $this->taxonomy($course->subcategory) : null,
            'creator' => $course->creator ? $this->userLite($course->creator) : null,
            'payment_plan' => $this->coursePaymentPlan($course),
            'membership_upsells' => $this->offers->membershipUpsells(),
            'sections' => $course->sections
                ->map(fn ($section): array => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'slug' => $section->slug,
                    'description' => $this->sanitizer->plainText($section->description),
                    'sort_order' => $section->sort_order,
                    'lessons' => $section->lessons
                        ->map(fn (CourseLesson $lesson): array => $this->lessonCard($lesson, $course))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'faqs' => $this->faqs($course->faqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lessonCard(CourseLesson $lesson, Course $course, ?User $user = null): array
    {
        $locked = $this->lessonIsLocked($course, $lesson, $user);

        return [
            'id' => $lesson->id,
            'course_id' => $course->id,
            'section_id' => $lesson->course_section_id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'url' => route('public.lessons.show', [$course->slug, $lesson->slug]),
            'order_number' => $lesson->order_number,
            'is_free' => (bool) $lesson->is_free,
            'is_paid' => (bool) $lesson->is_paid,
            'is_locked' => $locked,
            'preview' => $this->preview($lesson),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lessonDetail(CourseLesson $lesson, Course $course, ?User $user = null): array
    {
        $locked = $this->lessonIsLocked($course, $lesson, $user);

        return [
            ...$this->lessonCard($lesson, $course, $user),
            'content' => $locked ? null : $this->sanitizer->plainText($lesson->content),
            'video_type' => $this->enumValue($lesson->getAttribute('video_type')),
            'video_url' => $locked ? null : $lesson->video_url,
            'section' => $lesson->section ? [
                'id' => $lesson->section->id,
                'title' => $lesson->section->title,
                'slug' => $lesson->section->slug,
            ] : null,
            'allow_comments' => (bool) $lesson->allow_comments,
            'allow_questions' => (bool) $lesson->allow_questions,
            'faqs' => $this->faqs($lesson->faqs),
            'learning' => $this->lessonLearning($lesson, $course, $user, $locked),
            'community' => $this->lessonCommunity($lesson, $user, $locked),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blogCard(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => route('public.blog.show', $post->slug),
            'excerpt' => $this->sanitizer->plainText($post->excerpt),
            'is_featured' => (bool) $post->is_featured,
            'published_at' => $this->isoDate($post->getAttribute('published_at')),
            'category' => $post->category ? $this->taxonomy($post->category) : null,
            'author' => $post->author ? $this->userLite($post->author) : null,
            'featured_image' => $this->media($post->featuredImage),
            'social_share_image' => $this->socialShareImage($post),
            'tags' => $post->tags
                ->map(fn ($tag): array => $this->taxonomy($tag))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blogDetail(BlogPost $post): array
    {
        return [
            ...$this->blogCard($post),
            'content' => $this->sanitizer->plainText($post->content),
            'faqs' => $this->faqs($post->faqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'url' => route('public.pages.show', $page->slug),
            'excerpt' => $this->sanitizer->plainText($page->excerpt),
            'content' => $this->sanitizer->plainText($page->content),
            'template' => $page->template,
            'blocks' => $page->blocks
                ->map(fn ($block): array => [
                    'id' => $block->id,
                    'key' => $block->key,
                    'title' => $this->sanitizer->plainText($block->title),
                    'body' => $this->sanitizer->plainText($block->body),
                    'settings' => $block->settings,
                ])
                ->values()
                ->all(),
            'author' => $page->author ? $this->userLite($page->author) : null,
            'published_at' => $this->isoDate($page->getAttribute('published_at')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blogger(User $user): array
    {
        $profile = $user->bloggerProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'url' => route('public.bloggers.show', $user->id),
            'bio' => $this->sanitizer->plainText($profile?->bio),
            'expertise' => $this->sanitizer->plainText($profile?->expertise),
            'linkedin_url' => $profile?->linkedin_url,
            'website_url' => $profile?->website_url,
            'profile_photo_path' => $profile?->profile_photo_path,
            'post_count' => (int) ($user->getAttribute('blog_posts_count') ?? 0),
            'is_verified_creator' => (bool) $profile?->is_verified_creator,
            'is_verified_expert' => (bool) $user->instructorProfile?->is_verified_expert || $this->hasVerifiedExpertBadge($user),
            'badges' => $this->authorBadges($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function instructor(InstructorProfile $profile): array
    {
        $user = $profile->user;

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'url' => route('public.instructors.show', $profile->slug),
            'headline' => $this->sanitizer->plainText($profile->headline),
            'bio' => $this->sanitizer->plainText($profile->bio),
            'credentials' => $this->sanitizer->plainText($profile->credentials),
            'expertise' => $this->sanitizer->plainText($profile->expertise),
            'website_url' => $profile->website_url,
            'linkedin_url' => $profile->linkedin_url,
            'avatar' => $this->media($profile->avatar),
            'is_verified_expert' => (bool) $profile->is_verified_expert || ($user ? $this->hasVerifiedExpertBadge($user) : false),
            'badges' => $user ? $this->authorBadges($user) : [],
            'course_count' => (int) ($user?->getAttribute('courses_count') ?? 0),
            'post_count' => (int) ($user?->getAttribute('blog_posts_count') ?? 0),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public function paginator(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => $paginator->getCollection()->map($map)->values()->all(),
            'links' => $paginator->linkCollection()->values()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $faqs
     * @return array<int, array<string, mixed>>
     */
    public function faqs(Collection $faqs): array
    {
        return $faqs
            ->map(fn ($faq): array => [
                'id' => $faq->id,
                'question' => $this->sanitizer->plainText($faq->question),
                'answer' => $this->sanitizer->plainText($faq->answer),
            ])
            ->values()
            ->all();
    }

    public function lessonIsLocked(Course $course, CourseLesson $lesson, ?User $user = null): bool
    {
        return ! $this->access->canAccessLesson($user, $course, $lesson);
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonLearning(CourseLesson $lesson, Course $course, ?User $user, bool $locked): array
    {
        $progress = $user ? $user->lessonProgress()
            ->where('course_lesson_id', $lesson->id)
            ->first() : null;
        $bookmark = $user ? $user->lessonBookmarks()
            ->where('course_lesson_id', $lesson->id)
            ->first() : null;
        $notes = $user ? $user->lessonNotes()
            ->where('course_lesson_id', $lesson->id)
            ->latest()
            ->get() : collect();
        $availableAt = $this->access->lessonAvailableAt($user, $course, $lesson);

        return [
            'actions' => [
                'progress_url' => $user ? route('student.lessons.progress.store', $lesson->id) : null,
                'bookmark_url' => $user ? route('student.lessons.bookmarks.store', $lesson->id) : null,
                'note_url' => $user ? route('student.lessons.notes.store', $lesson->id) : null,
            ],
            'drip' => [
                'is_released' => $this->access->lessonIsReleased($user, $course, $lesson),
                'available_at' => $this->isoDate($availableAt),
            ],
            'progress' => $progress ? [
                'status' => $this->enumValue($progress->status),
                'progress_seconds' => $progress->progress_seconds,
                'duration_seconds' => $progress->duration_seconds,
                'progress_percent' => $progress->progress_percent,
                'completed_at' => $this->isoDate($progress->completed_at),
            ] : null,
            'bookmark' => $bookmark ? [
                'id' => $bookmark->id,
                'label' => $bookmark->label,
                'saved_at' => $this->isoDate($bookmark->saved_at),
            ] : null,
            'notes' => $notes
                ->map(fn ($note): array => [
                    'id' => $note->id,
                    'body' => $this->sanitizer->plainText($note->body),
                    'created_at' => $this->isoDate($note->created_at),
                ])
                ->values()
                ->all(),
            'resources' => $locked ? [] : $lesson->resources
                ->map(fn (CourseResource $resource): array => [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'description' => $this->sanitizer->plainText($resource->description),
                    'type' => $resource->type,
                    'access_level' => $this->enumValue($resource->access_level),
                    'download_url' => $user && $this->access->canAccessResource($user, $resource)
                        ? route('student.resources.download', $resource->id)
                        : null,
                ])
                ->values()
                ->all(),
            'quizzes' => $locked ? [] : $lesson->quizzes
                ->map(fn ($quiz): array => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $this->sanitizer->plainText($quiz->description),
                    'pass_score' => $quiz->pass_score,
                    'max_attempts' => $quiz->max_attempts,
                    'time_limit_minutes' => $quiz->time_limit_minutes,
                    'is_required' => (bool) $quiz->is_required,
                    'attempt_url' => $user ? route('student.quizzes.attempts.store', $quiz->id) : null,
                    'questions' => $quiz->questions
                        ->map(fn (QuizQuestion $question): array => [
                            'id' => $question->id,
                            'question' => $this->sanitizer->plainText($question->question),
                            'type' => $this->enumValue($question->type),
                            'points' => $question->points,
                            'options' => $question->options,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'assignments' => $locked ? [] : $lesson->assignments
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'instructions' => $this->sanitizer->plainText($assignment->instructions),
                    'pass_score' => $assignment->pass_score,
                    'max_points' => $assignment->max_points,
                    'due_days_after_enrollment' => $assignment->due_days_after_enrollment,
                    'allow_file_uploads' => (bool) $assignment->allow_file_uploads,
                    'is_required' => (bool) $assignment->is_required,
                    'submission_url' => $user ? route('student.assignments.submissions.store', $assignment->id) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonCommunity(CourseLesson $lesson, ?User $user, bool $locked): array
    {
        $questions = $locked ? collect() : $lesson->questions;

        return [
            'actions' => [
                'question_url' => $user && ! $locked && (bool) $lesson->allow_questions
                    ? route('student.lessons.questions.store', $lesson->id)
                    : null,
            ],
            'questions' => $questions
                ->map(fn (CourseQuestion $question): array => [
                    'id' => $question->id,
                    'title' => $question->title,
                    'body' => $this->sanitizer->plainText($question->body),
                    'author' => $question->user ? $this->userLite($question->user) : null,
                    'created_at' => $this->isoDate($question->created_at),
                    'answer_url' => $user ? route('student.questions.answers.store', $question->id) : null,
                    'report_url' => $user ? route('student.community.questions.reports.store', $question->id) : null,
                    'accepted_answer_id' => $question->accepted_answer_id,
                    'answers' => $question->answers
                        ->map(fn ($answer): array => [
                            'id' => $answer->id,
                            'body' => $this->sanitizer->plainText($answer->body),
                            'author' => $answer->user ? $this->userLite($answer->user) : null,
                            'upvotes_count' => $answer->upvotes_count,
                            'is_accepted' => (int) $question->accepted_answer_id === (int) $answer->id,
                            'accept_url' => $user && (int) $question->user_id === (int) $user->id
                                ? route('student.questions.answers.accept', [$question->id, $answer->id])
                                : null,
                            'reaction_url' => $user ? route('student.community.answers.reactions.store', $answer->id) : null,
                            'report_url' => $user ? route('student.community.answers.reports.store', $answer->id) : null,
                            'created_at' => $this->isoDate($answer->created_at),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function media(?MediaAsset $media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'id' => $media->id,
            'url' => $media->url ? $this->absoluteUrl($media->url) : null,
            'title' => $media->title,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function socialShareImage(Model $model): ?array
    {
        if (! method_exists($model, 'activeSocialShareImage')) {
            return null;
        }

        $image = $model->relationLoaded('activeSocialShareImage')
            ? $model->getRelation('activeSocialShareImage')
            : $model->activeSocialShareImage()->with('media')->first();

        if (! $image instanceof SocialShareImage) {
            return null;
        }

        $media = $this->media($image->media);
        $url = $image->image_url ?: ($media['url'] ?? null);

        return [
            'id' => $image->id,
            'url' => is_string($url) && filled($url) ? $this->absoluteUrl($url) : null,
            'title' => $image->title,
            'alt_text' => $image->alt_text,
            'template' => $image->template,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function coursePaymentPlan(Course $course): ?array
    {
        $price = $course->paymentProduct?->prices
            ->first(fn (PaymentPrice $price): bool => (bool) $price->is_active && filled($price->paddle_price_id));

        if (! $price) {
            return null;
        }

        return [
            'id' => $price->id,
            'name' => $price->name,
            'formatted_amount' => $price->formattedAmount(),
            'checkout_url' => route('checkout.courses.store', $course->slug),
            'accepts_gifts' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxonomy(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->getAttribute('name'),
            'slug' => $model->getAttribute('slug'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userLite(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'url' => $user->bloggerProfile?->isApproved() ? route('public.bloggers.show', $user->id) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function authorBadges(User $user): array
    {
        $badges = $user->relationLoaded('authorBadges')
            ? $user->authorBadges
            : $user->authorBadges()->where('is_active', true)->orderBy('sort_order')->get();

        return $badges
            ->where('is_active', true)
            ->map(fn ($badge): array => [
                'id' => $badge->id,
                'name' => $badge->name,
                'slug' => $badge->slug,
                'description' => $this->sanitizer->plainText($badge->description),
                'icon' => $badge->icon,
                'color' => $badge->color,
                'marks_verified_expert' => (bool) $badge->marks_verified_expert,
            ])
            ->values()
            ->all();
    }

    private function hasVerifiedExpertBadge(User $user): bool
    {
        $badges = $user->relationLoaded('authorBadges')
            ? $user->authorBadges
            : $user->authorBadges()->where('is_active', true)->get();

        return $badges->contains(fn ($badge): bool => (bool) $badge->marks_verified_expert);
    }

    private function preview(CourseLesson $lesson): ?string
    {
        if (blank($lesson->content)) {
            return null;
        }

        return $this->sanitizer->preview((string) $lesson->content, (int) ($lesson->preview_word_limit ?: 80));
    }

    private function formatPrice(float $price, bool $isFree): string
    {
        return $isFree || $price <= 0 ? 'Free' : '$'.number_format($price, 2);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? $value : null;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }

    private function absoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
