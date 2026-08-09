<?php

namespace App\Http\Controllers\Creator;

use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\ApprovalHistory;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\RevenueShareRule;
use App\Models\User;
use App\Services\Creators\CreatorAgreementService;
use App\Services\Creators\CreatorAnalyticsService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class CreatorDashboardController extends Controller
{
    public function __invoke(Request $request, CreatorAnalyticsService $analytics, CreatorAgreementService $agreements): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['bloggerProfile.reviewer', 'instructorProfile', 'authorBadges']);

        $blogIds = BlogPost::query()->where('author_id', $user->id)->pluck('id');
        $courseIds = Course::query()->where('created_by', $user->id)->pluck('id');

        return Inertia::render('creator/Dashboard', [
            'profile' => $this->profilePayload($user),
            'agreement' => [
                'version' => $agreements->currentVersion(),
                'accepted' => $agreements->hasAccepted($user),
                'url' => route('creator.agreement.show', absolute: false),
            ],
            'summary' => $this->summary($user),
            'analytics' => $analytics->summaryFor($user),
            'notifications' => $this->notifications($user),
            'unread_notification_count' => $this->unreadNotificationCount($user),
            'recent_content' => $this->recentContent($user),
            'approval_activity' => $this->approvalActivity($blogIds->all(), $courseIds->all()),
            'delete_requests' => $this->deleteRequests($user),
            'revisions' => $this->editorialRevisions($user),
            'revenue_rules' => $this->revenueRules($user),
            'actions' => [
                'profile_url' => route('creator.profile.edit', absolute: false),
                'blogs_url' => route('creator.blogs.index', absolute: false),
                'courses_url' => route('creator.courses.index', absolute: false),
                'create_blog_url' => route('creator.blogs.create', absolute: false),
                'create_course_url' => route('creator.courses.create', absolute: false),
            ],
            'capabilities' => [
                'can_create_blogs' => $user->can('create', BlogPost::class),
                'can_create_courses' => $user->can('create', Course::class),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        $profile = $user->bloggerProfile;

        return [
            'name' => $user->name,
            'blogger_status' => $this->enumValue($profile?->getAttribute('status')),
            'instructor_status' => $this->enumValue($user->instructorProfile?->getAttribute('status')),
            'instructor_display_name' => $user->instructorProfile?->display_name,
            'bio' => $profile?->bio,
            'expertise' => $profile?->expertise,
            'linkedin_url' => $profile?->linkedin_url,
            'website_url' => $profile?->website_url,
            'application_reason' => $profile?->application_reason,
            'admin_notes' => $profile?->admin_notes,
            'reviewed_at' => $this->dateString($profile?->reviewed_at),
            'reviewer' => $profile?->reviewer?->name,
            'is_verified_expert' => (bool) $user->instructorProfile?->is_verified_expert
                || $user->authorBadges->contains(fn ($badge): bool => (bool) $badge->marks_verified_expert),
            'badges' => $user->authorBadges
                ->where('is_active', true)
                ->map(fn ($badge): array => [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'color' => $badge->color,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(User $user): array
    {
        $blogCounts = $this->countsByStatus(BlogPost::query()->where('author_id', $user->id)->get(['status']));
        $courseCounts = $this->countsByStatus(Course::query()->where('created_by', $user->id)->get(['status']));
        $deleteRequests = CreatorContentDeletionRequest::query()
            ->where('requester_id', $user->id)
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->count();

        return [
            'drafts' => $this->sumStatus($blogCounts, $courseCounts, PublishStatus::Draft),
            'pending_approval' => $this->sumStatus($blogCounts, $courseCounts, PublishStatus::Pending)
                + (int) ($blogCounts[PublishStatus::Submitted->value] ?? 0)
                + (int) ($blogCounts[PublishStatus::Approved->value] ?? 0),
            'published' => $this->sumStatus($blogCounts, $courseCounts, PublishStatus::Published),
            'changes_requested' => $this->sumStatus($blogCounts, $courseCounts, PublishStatus::Rejected)
                + (int) ($blogCounts[PublishStatus::ChangesRequested->value] ?? 0),
            'delete_requests' => $deleteRequests,
            'blog_drafts' => $blogCounts[PublishStatus::Draft->value] ?? 0,
            'course_drafts' => $courseCounts[PublishStatus::Draft->value] ?? 0,
            'blog_pending' => (int) ($blogCounts[PublishStatus::Pending->value] ?? 0)
                + (int) ($blogCounts[PublishStatus::Submitted->value] ?? 0)
                + (int) ($blogCounts[PublishStatus::Approved->value] ?? 0),
            'course_pending' => $courseCounts[PublishStatus::Pending->value] ?? 0,
            'blog_published' => $blogCounts[PublishStatus::Published->value] ?? 0,
            'course_published' => $courseCounts[PublishStatus::Published->value] ?? 0,
        ];
    }

    /**
     * @param  iterable<int, BlogPost|Course>  $records
     * @return array<string, int>
     */
    private function countsByStatus(iterable $records): array
    {
        return collect($records)
            ->map(fn (Model $record): string => $this->enumValue($record->getAttribute('status')) ?? 'unknown')
            ->countBy()
            ->all();
    }

    /**
     * @param  array<string, int>  $blogCounts
     * @param  array<string, int>  $courseCounts
     */
    private function sumStatus(array $blogCounts, array $courseCounts, PublishStatus $status): int
    {
        return (int) ($blogCounts[$status->value] ?? 0)
            + (int) ($courseCounts[$status->value] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentContent(User $user): array
    {
        $blogs = BlogPost::query()
            ->where('author_id', $user->id)
            ->with(['category'])
            ->withCount('comments')
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn (BlogPost $post): array => $this->contentCard('blog', $post, [
                'engagement_label' => $post->comments_count.' comments',
                'manage_url' => route('creator.blogs.edit', $post, absolute: false),
            ]));

        $courses = Course::query()
            ->where('created_by', $user->id)
            ->with(['category'])
            ->withCount(['lessons', 'enrollments'])
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Course $course): array => $this->contentCard('course', $course, [
                'engagement_label' => $course->lessons_count.' lessons / '.$course->enrollments_count.' enrollments',
                'manage_url' => route('creator.courses.edit', $course, absolute: false),
            ]));

        return $blogs
            ->toBase()
            ->merge($courses)
            ->sortByDesc('updated_at')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notifications(User $user): array
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->notificationPayload($notification))
            ->all();
    }

    private function unreadNotificationCount(User $user): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'event' => $this->stringFrom($data, 'event', 'notification'),
            'severity' => $this->stringFrom($data, 'severity', 'info'),
            'title' => $this->stringFrom($data, 'title', 'Dashboard alert'),
            'message' => $this->stringFrom($data, 'message', ''),
            'note' => $this->nullableStringFrom($data, 'note'),
            'content_type' => $this->nullableStringFrom($data, 'content_type'),
            'content_id' => isset($data['content_id']) && is_numeric($data['content_id']) ? (int) $data['content_id'] : null,
            'content_title' => $this->nullableStringFrom($data, 'content_title'),
            'status' => $this->nullableStringFrom($data, 'status'),
            'action_url' => $this->nullableStringFrom($data, 'action_url'),
            'read_at' => $this->dateString($notification->read_at),
            'created_at' => $this->dateString($notification->created_at),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function contentCard(string $type, BlogPost|Course $content, array $extra = []): array
    {
        return [
            'id' => $content->id,
            'type' => $type,
            'title' => $content->title,
            'status' => $this->enumValue($content->getAttribute('status')),
            'category' => $content->category?->name,
            'published_at' => $this->dateString($content->getAttribute('published_at')),
            'updated_at' => $this->dateString($content->getAttribute('updated_at')),
            'rejection_reason' => $content->rejection_reason,
            'admin_notes' => $content->admin_notes,
            'public_url' => $content->isPublished()
                ? ($type === 'blog'
                    ? route('public.blog.show', $content->slug, absolute: false)
                    : route('public.courses.show', $content->slug, absolute: false))
                : null,
            ...$extra,
        ];
    }

    /**
     * @param  array<int, int>  $blogIds
     * @param  array<int, int>  $courseIds
     * @return array<int, array<string, mixed>>
     */
    private function approvalActivity(array $blogIds, array $courseIds): array
    {
        return ApprovalHistory::query()
            ->with('actor')
            ->where(function ($query) use ($blogIds, $courseIds): void {
                $query
                    ->where(function ($query) use ($blogIds): void {
                        $query->where('subject_type', (new BlogPost)->getMorphClass())
                            ->whereIn('subject_id', $blogIds ?: [0]);
                    })
                    ->orWhere(function ($query) use ($courseIds): void {
                        $query->where('subject_type', (new Course)->getMorphClass())
                            ->whereIn('subject_id', $courseIds ?: [0]);
                    });
            })
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (ApprovalHistory $history): array => [
                'id' => $history->id,
                'decision' => $history->decision,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'note' => $history->note,
                'actor' => $history->actor?->name,
                'created_at' => $this->dateString($history->created_at),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deleteRequests(User $user): array
    {
        return CreatorContentDeletionRequest::query()
            ->where('requester_id', $user->id)
            ->with('content')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (CreatorContentDeletionRequest $request): array => $this->deleteRequestPayload($request))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteRequestPayload(CreatorContentDeletionRequest $request): array
    {
        $content = $request->content;

        return [
            'id' => $request->id,
            'status' => $this->enumValue($request->getAttribute('status')),
            'reason' => $request->reason,
            'admin_note' => $request->admin_note,
            'content_title' => $content instanceof BlogPost || $content instanceof Course ? $content->title : null,
            'content_type' => $content instanceof Course ? 'course' : 'blog',
            'created_at' => $this->dateString($request->created_at),
            'decided_at' => $this->dateString($request->decided_at),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function editorialRevisions(User $user): array
    {
        return EditorialRevision::query()
            ->where('author_id', $user->id)
            ->with(['comments'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (EditorialRevision $revision): array => [
                'id' => $revision->id,
                'title' => $revision->title,
                'status' => $this->enumValue($revision->getAttribute('status')),
                'comments_count' => $revision->comments->count(),
                'submitted_at' => $this->dateString($revision->getAttribute('submitted_at')),
                'scheduled_at' => $this->dateString($revision->getAttribute('scheduled_at')),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueRules(User $user): array
    {
        return RevenueShareRule::query()
            ->where('user_id', $user->id)
            ->with('course')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (RevenueShareRule $rule): array => [
                'id' => $rule->id,
                'course_title' => $rule->course?->title,
                'type' => $this->enumValue($rule->getAttribute('type')),
                'status' => $this->enumValue($rule->getAttribute('status')),
                'share_percent' => (float) $rule->share_percent,
                'currency' => $rule->currency,
            ])
            ->all();
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringFrom(array $data, string $key, string $fallback): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : $fallback;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullableStringFrom(array $data, string $key): ?string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : null;
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : (is_string($value) ? $value : null);
    }
}
