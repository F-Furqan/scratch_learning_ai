<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BloggerStatus;
use App\Enums\CopyrightTakedownStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\ApprovalHistory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CopyrightTakedownRequest;
use App\Models\Course;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Services\Admin\ApprovalRecorder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminReviewCenterController extends Controller
{
    private const REVIEW_CHECKLIST_LABELS = [
        'ownership_rights' => 'Rights and ownership checked',
        'editorial_quality' => 'Editorial quality checked',
        'seo_metadata' => 'SEO metadata checked',
        'policy_safety' => 'Policy and safety checked',
    ];

    private const BLOG_DIFF_FIELDS = [
        'blog_category_id' => 'Category',
        'title' => 'Title',
        'excerpt' => 'Excerpt',
        'content' => 'Content',
        'seo_title' => 'SEO title',
        'seo_description' => 'SEO description',
    ];

    private const COURSE_DIFF_FIELDS = [
        'course_category_id' => 'Category',
        'course_subcategory_id' => 'Subcategory',
        'thumbnail_media_id' => 'Thumbnail media',
        'title' => 'Title',
        'short_description' => 'Short description',
        'description' => 'Description',
        'intro_video_url' => 'Intro video URL',
        'level' => 'Level',
        'language' => 'Language',
        'price' => 'Price',
        'is_free' => 'Free course',
        'seo_title' => 'SEO title',
        'seo_description' => 'SEO description',
        'ownership_video_media_id' => 'Ownership video file',
        'ownership_video_url' => 'Ownership video URL',
        'ownership_statement' => 'Ownership statement',
        'ownership_confirmed_at' => 'Ownership confirmed at',
    ];

    public function __construct(
        private readonly ApprovalRecorder $approvals,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorizeAnyReviewAccess($request);

        $sections = array_values(array_filter([
            $this->can($request, PermissionName::ApproveBloggers) ? $this->creatorApplicationsSection() : null,
            $this->can($request, PermissionName::ManageBlogs) ? $this->blogApprovalsSection() : null,
            $this->can($request, PermissionName::ManageCourses) ? $this->courseApprovalsSection() : null,
            $this->canAny($request, [PermissionName::ManageBlogs, PermissionName::ManageCourses]) ? $this->revisionApprovalsSection($request) : null,
            $this->canAny($request, [PermissionName::ManageBlogs, PermissionName::ManageCourses]) ? $this->deleteRequestsSection($request) : null,
            $this->canAny($request, [PermissionName::ManageCms, PermissionName::ManageBlogs, PermissionName::ManageCourses]) ? $this->copyrightTakedownsSection() : null,
            $this->can($request, PermissionName::ManageCourses) ? $this->ownershipVideosSection() : null,
        ]));

        return Inertia::render('admin/ReviewCenter', [
            'summary' => [
                'total_pending' => array_sum(array_map(fn (array $section): int => (int) $section['count'], $sections)),
                'sections' => count($sections),
            ],
            'sections' => $sections,
        ]);
    }

    public function approveCreatorApplication(Request $request, BloggerProfile $profile): RedirectResponse
    {
        return $this->decideCreatorApplication(
            $request,
            $profile,
            BloggerStatus::Approved,
            'creator_application_approved',
            'creator-application-approved',
        );
    }

    public function rejectCreatorApplication(Request $request, BloggerProfile $profile): RedirectResponse
    {
        return $this->decideCreatorApplication(
            $request,
            $profile,
            BloggerStatus::Rejected,
            'creator_application_rejected',
            'creator-application-rejected',
        );
    }

    public function markCopyrightTakedownReviewing(Request $request, CopyrightTakedownRequest $takedownRequest): RedirectResponse
    {
        return $this->decideCopyrightTakedown(
            $request,
            $takedownRequest,
            CopyrightTakedownStatus::Reviewing,
            'copyright_takedown_reviewing',
            'copyright-takedown-reviewing',
        );
    }

    public function resolveCopyrightTakedown(Request $request, CopyrightTakedownRequest $takedownRequest): RedirectResponse
    {
        return $this->decideCopyrightTakedown(
            $request,
            $takedownRequest,
            CopyrightTakedownStatus::Resolved,
            'copyright_takedown_resolved',
            'copyright-takedown-resolved',
        );
    }

    public function dismissCopyrightTakedown(Request $request, CopyrightTakedownRequest $takedownRequest): RedirectResponse
    {
        return $this->decideCopyrightTakedown(
            $request,
            $takedownRequest,
            CopyrightTakedownStatus::Dismissed,
            'copyright_takedown_dismissed',
            'copyright-takedown-dismissed',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function creatorApplicationsSection(): array
    {
        $query = BloggerProfile::query()
            ->where('status', BloggerStatus::Pending->value);

        $items = (clone $query)
            ->with(['user', 'reviewer', 'approvalHistories.actor'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (BloggerProfile $profile): array => [
                'key' => 'creator_application:'.$profile->id,
                'id' => $profile->id,
                'type' => 'Creator Application',
                'title' => $profile->user?->name ?: 'Creator application #'.$profile->id,
                'subtitle' => $profile->application_reason,
                'status' => $this->statusValue($profile->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($profile->created_at),
                'meta' => [
                    $this->meta('Email', $profile->user?->email),
                    $this->meta('Expertise', $profile->expertise),
                    $this->meta('LinkedIn', $profile->linkedin_url, $profile->linkedin_url),
                    $this->meta('Website', $profile->website_url, $profile->website_url),
                ],
                'history' => $this->historyRows($profile),
                'actions' => [
                    $this->action('Approve', route('admin.review-center.creator-applications.approve', $profile, false), 'success'),
                    $this->action('Reject', route('admin.review-center.creator-applications.reject', $profile, false), 'danger'),
                ],
            ])
            ->values()
            ->all();

        return $this->section(
            'creator_applications',
            'Creator Applications',
            'New creator profiles waiting for approval.',
            $query->count(),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function blogApprovalsSection(): array
    {
        $statuses = [
            PublishStatus::Submitted->value,
            PublishStatus::Approved->value,
            PublishStatus::ChangesRequested->value,
        ];
        $query = BlogPost::query()->whereIn('status', $statuses);

        $items = (clone $query)
            ->with(['author', 'category', 'approvalHistories.actor'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (BlogPost $post): array => [
                'key' => 'blog:'.$post->id,
                'id' => $post->id,
                'type' => 'Blog Approval',
                'title' => $post->title,
                'subtitle' => $post->excerpt,
                'status' => $this->statusValue($post->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($post->updated_at),
                'meta' => [
                    $this->meta('Author', $post->author?->name),
                    $this->meta('Category', $post->category?->name),
                    $this->meta('Published At', $this->dateTimeString($post->published_at)),
                ],
                'history' => $this->historyRows($post),
                'actions' => $this->blogActions($post),
            ])
            ->values()
            ->all();

        return $this->section(
            'blog_approvals',
            'Blog Approvals',
            'Submitted articles, approved drafts, and change requests.',
            $query->count(),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function courseApprovalsSection(): array
    {
        $query = Course::query()->where('status', PublishStatus::Pending->value);

        $items = (clone $query)
            ->with(['creator', 'category', 'subcategory', 'ownershipVideo', 'approvalHistories.actor'])
            ->withCount(['sections', 'lessons', 'resources', 'faqs'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Course $course): array => [
                'key' => 'course:'.$course->id,
                'id' => $course->id,
                'type' => 'Course Approval',
                'title' => $course->title,
                'subtitle' => $course->short_description,
                'status' => $this->statusValue($course->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($course->updated_at),
                'meta' => [
                    $this->meta('Creator', $course->creator?->name),
                    $this->meta('Category', $course->category?->name),
                    $this->meta('Curriculum', sprintf(
                        '%s sections / %s lessons / %s resources / %s FAQs',
                        $course->sections_count ?? 0,
                        $course->lessons_count ?? 0,
                        $course->resources_count ?? 0,
                        $course->faqs_count ?? 0,
                    )),
                    $this->meta('Ownership Proof', $this->ownershipLabel($course), $this->ownershipUrl($course)),
                ],
                'history' => $this->historyRows($course),
                'actions' => $this->courseActions($course),
            ])
            ->values()
            ->all();

        return $this->section(
            'course_approvals',
            'Course Approvals',
            'Courses submitted with curriculum and ownership material.',
            $query->count(),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionApprovalsSection(Request $request): array
    {
        $query = EditorialRevision::query()
            ->whereIn('status', [
                EditorialRevisionStatus::Submitted->value,
                EditorialRevisionStatus::ChangesRequested->value,
            ]);

        $items = (clone $query)
            ->with(['author', 'reviewer', 'editorialable', 'approvalHistories.actor'])
            ->withCount('comments')
            ->latest('submitted_at')
            ->limit(20)
            ->get()
            ->filter(fn (EditorialRevision $revision): bool => $this->canReviewRevision($request, $revision))
            ->map(fn (EditorialRevision $revision): array => [
                'key' => 'revision:'.$revision->id,
                'id' => $revision->id,
                'type' => 'Revision Approval',
                'title' => $revision->title,
                'subtitle' => $revision->summary,
                'status' => $this->statusValue($revision->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($revision->submitted_at),
                'meta' => [
                    $this->meta('Author', $revision->author?->name),
                    $this->meta('Content', $this->contentLabel($revision->editorialable)),
                    $this->meta('Comments', (string) ($revision->comments_count ?? 0)),
                ],
                'history' => $this->historyRows($revision),
                'diff' => $this->revisionDiff($revision),
                'actions' => $this->revisionActions($revision),
            ])
            ->values()
            ->all();

        return $this->section(
            'revision_approvals',
            'Revision Approvals',
            'Published content edits waiting for review.',
            count($items),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteRequestsSection(Request $request): array
    {
        $contentTypes = $this->deletionContentTypes($request);
        $query = CreatorContentDeletionRequest::query()
            ->where('status', CreatorContentDeletionStatus::Pending->value)
            ->whereIn('content_type', $contentTypes);

        $items = (clone $query)
            ->with(['requester', 'content', 'approvalHistories.actor'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CreatorContentDeletionRequest $deletionRequest): array => [
                'key' => 'delete_request:'.$deletionRequest->id,
                'id' => $deletionRequest->id,
                'type' => 'Delete Request',
                'title' => $this->contentLabel($deletionRequest->content),
                'subtitle' => $deletionRequest->reason,
                'status' => $this->statusValue($deletionRequest->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($deletionRequest->created_at),
                'meta' => [
                    $this->meta('Requester', $deletionRequest->requester?->name),
                    $this->meta('Content Type', $this->contentTypeLabel($deletionRequest->content)),
                    $this->meta('Requested At', $this->dateTimeString($deletionRequest->created_at)),
                ],
                'history' => $this->historyRows($deletionRequest),
                'actions' => $this->deleteRequestActions($deletionRequest),
            ])
            ->values()
            ->all();

        return $this->section(
            'delete_requests',
            'Delete Requests',
            'Creator removal requests waiting for an admin decision.',
            $query->count(),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function copyrightTakedownsSection(): array
    {
        $query = CopyrightTakedownRequest::query()
            ->whereIn('status', [
                CopyrightTakedownStatus::Submitted->value,
                CopyrightTakedownStatus::Reviewing->value,
            ]);

        $items = (clone $query)
            ->with(['reportable', 'reviewer', 'approvalHistories.actor'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CopyrightTakedownRequest $takedownRequest): array => [
                'key' => 'copyright_takedown:'.$takedownRequest->id,
                'id' => $takedownRequest->id,
                'type' => 'Copyright Takedown',
                'title' => $takedownRequest->content_title ?: $this->contentLabel($takedownRequest->reportable),
                'subtitle' => $takedownRequest->description,
                'status' => $this->statusValue($takedownRequest->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($takedownRequest->created_at),
                'meta' => [
                    $this->meta('Claimant', $takedownRequest->claimant_name),
                    $this->meta('Email', $takedownRequest->claimant_email),
                    $this->meta('Rights Owner', $takedownRequest->rights_owner),
                    $this->meta('Reported URL', $takedownRequest->infringing_url, $takedownRequest->infringing_url),
                    $this->meta('Original Work', $takedownRequest->original_work_url, $takedownRequest->original_work_url),
                    $this->meta('Matched Content', $this->contentLabel($takedownRequest->reportable)),
                ],
                'history' => $this->historyRows($takedownRequest),
                'actions' => $this->copyrightTakedownActions($takedownRequest),
            ])
            ->values()
            ->all();

        return $this->section(
            'copyright_takedowns',
            'Copyright Takedowns',
            'Legal reports waiting for review, resolution, or dismissal.',
            $query->count(),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ownershipVideosSection(): array
    {
        $query = Course::query()
            ->where('status', PublishStatus::Pending->value)
            ->where(fn ($query) => $query
                ->whereNotNull('ownership_video_media_id')
                ->orWhereNotNull('ownership_video_url')
                ->orWhereNotNull('ownership_statement'));

        $items = (clone $query)
            ->with(['creator', 'ownershipVideo', 'approvalHistories.actor'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Course $course): array => [
                'key' => 'ownership_video:'.$course->id,
                'id' => $course->id,
                'type' => 'Ownership Video',
                'title' => $course->title,
                'subtitle' => $course->ownership_statement,
                'status' => $this->statusValue($course->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($course->ownership_confirmed_at),
                'meta' => [
                    $this->meta('Creator', $course->creator?->name),
                    $this->meta('Video', $this->ownershipLabel($course), $this->ownershipUrl($course)),
                    $this->meta('Confirmed At', $this->dateTimeString($course->ownership_confirmed_at)),
                ],
                'history' => $this->historyRows($course),
                'actions' => $this->courseActions($course),
            ])
            ->values()
            ->all();

        return $this->section(
            'ownership_videos',
            'Ownership Videos',
            'Creator ownership confirmations attached to submitted courses.',
            $query->count(),
            $items,
        );
    }

    private function decideCreatorApplication(Request $request, BloggerProfile $profile, BloggerStatus $to, string $decision, string $flash): RedirectResponse
    {
        abort_unless($request->user()?->can(PermissionName::ApproveBloggers->value), 403);
        abort_unless($this->statusValue($profile->getAttribute('status')) === BloggerStatus::Pending->value, 422, 'Only pending creator applications can be decided.');

        $note = $this->note($request);

        DB::transaction(function () use ($request, $profile, $to, $decision, $note): void {
            $from = $profile->getAttribute('status');

            $profile->forceFill([
                'status' => $to,
                'is_verified_creator' => $to === BloggerStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_notes' => $note ?: $profile->admin_notes,
            ])->save();

            if ($to === BloggerStatus::Approved) {
                $profile->user->assignRole(RoleName::Blogger->value);
            }

            $this->approvals->record($request, $profile, $decision, $from, $to, $note);
        });

        return back()->with('status', $flash);
    }

    private function decideCopyrightTakedown(
        Request $request,
        CopyrightTakedownRequest $takedownRequest,
        CopyrightTakedownStatus $to,
        string $decision,
        string $flash,
    ): RedirectResponse {
        abort_unless($this->canAny($request, [
            PermissionName::ManageCms,
            PermissionName::ManageBlogs,
            PermissionName::ManageCourses,
        ]), 403);
        abort_unless(in_array($this->statusValue($takedownRequest->getAttribute('status')), [
            CopyrightTakedownStatus::Submitted->value,
            CopyrightTakedownStatus::Reviewing->value,
        ], true), 422, 'Only submitted or reviewing takedown requests can be decided.');

        $note = $this->note($request);

        DB::transaction(function () use ($request, $takedownRequest, $to, $decision, $note): void {
            $from = $takedownRequest->getAttribute('status');

            $takedownRequest->forceFill([
                'status' => $to,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'resolution_note' => filled($note) ? $note : $takedownRequest->resolution_note,
            ])->save();

            $this->approvals->record(
                $request,
                $takedownRequest,
                $decision,
                $from,
                $to,
                $note,
                [
                    'claimant_email' => $takedownRequest->claimant_email,
                    'reportable_type' => $takedownRequest->reportable_type,
                    'reportable_id' => $takedownRequest->reportable_id,
                ],
            );
        });

        return back()->with('status', $flash);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function blogActions(BlogPost $post): array
    {
        $status = $this->statusValue($post->getAttribute('status'));

        if ($status === PublishStatus::Submitted->value) {
            return [
                $this->action('Approve', route('admin.blog-workflow.blogs.approve', $post, false), 'success'),
                $this->action('Changes', route('admin.blog-workflow.blogs.changes-requested', $post, false), 'warning'),
                $this->action('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        if ($status === PublishStatus::Approved->value) {
            return [
                $this->action('Publish', route('admin.blog-workflow.blogs.publish', $post, false), 'success'),
                $this->action('Changes', route('admin.blog-workflow.blogs.changes-requested', $post, false), 'warning'),
                $this->action('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        if ($status === PublishStatus::ChangesRequested->value) {
            return [
                $this->action('Approve', route('admin.blog-workflow.blogs.approve', $post, false), 'success'),
                $this->action('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        return [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function courseActions(Course $course): array
    {
        if ($this->statusValue($course->getAttribute('status')) !== PublishStatus::Pending->value) {
            return [];
        }

        return [
            $this->action('Approve', route('admin.course-workflow.courses.approve', $course, false), 'success'),
            $this->action('Changes', route('admin.course-workflow.courses.changes-requested', $course, false), 'warning'),
            $this->action('Reject', route('admin.course-workflow.courses.reject', $course, false), 'danger'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function revisionActions(EditorialRevision $revision): array
    {
        $content = $revision->editorialable;
        $routePrefix = match (true) {
            $content instanceof BlogPost => 'admin.blog-workflow.revisions.',
            $content instanceof Course => 'admin.course-workflow.revisions.',
            default => null,
        };

        if ($routePrefix === null) {
            return [];
        }

        $status = $this->statusValue($revision->getAttribute('status'));

        if ($status === EditorialRevisionStatus::Submitted->value) {
            return [
                $this->action('Apply', route($routePrefix.'approve', $revision, false), 'success'),
                $this->action('Changes', route($routePrefix.'changes-requested', $revision, false), 'warning'),
                $this->action('Reject', route($routePrefix.'reject', $revision, false), 'danger'),
            ];
        }

        if ($status === EditorialRevisionStatus::ChangesRequested->value) {
            return [
                $this->action('Apply', route($routePrefix.'approve', $revision, false), 'success'),
                $this->action('Reject', route($routePrefix.'reject', $revision, false), 'danger'),
            ];
        }

        return [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function deleteRequestActions(CreatorContentDeletionRequest $deletionRequest): array
    {
        $content = $deletionRequest->content;

        if ($content instanceof BlogPost) {
            return [
                $this->action('Trash', route('admin.blog-workflow.deletions.approve', $deletionRequest, false), 'danger'),
                $this->action('Keep', route('admin.blog-workflow.deletions.reject', $deletionRequest, false), 'warning'),
            ];
        }

        if ($content instanceof Course) {
            return [
                $this->action('Trash', route('admin.course-workflow.deletions.approve', $deletionRequest, false), 'danger'),
                $this->action('Keep', route('admin.course-workflow.deletions.reject', $deletionRequest, false), 'warning'),
            ];
        }

        return [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function copyrightTakedownActions(CopyrightTakedownRequest $takedownRequest): array
    {
        $actions = [];

        if ($this->statusValue($takedownRequest->getAttribute('status')) === CopyrightTakedownStatus::Submitted->value) {
            $actions[] = $this->action('Reviewing', route('admin.review-center.copyright-takedowns.reviewing', $takedownRequest, false), 'warning');
        }

        $actions[] = $this->action('Resolve', route('admin.review-center.copyright-takedowns.resolve', $takedownRequest, false), 'success');
        $actions[] = $this->action('Dismiss', route('admin.review-center.copyright-takedowns.dismiss', $takedownRequest, false), 'danger');

        return $actions;
    }

    private function canReviewRevision(Request $request, EditorialRevision $revision): bool
    {
        $content = $revision->editorialable;

        return ($content instanceof BlogPost && $this->can($request, PermissionName::ManageBlogs))
            || ($content instanceof Course && $this->can($request, PermissionName::ManageCourses));
    }

    /**
     * @return list<string>
     */
    private function deletionContentTypes(Request $request): array
    {
        $types = [];

        if ($this->can($request, PermissionName::ManageBlogs)) {
            $types[] = (new BlogPost)->getMorphClass();
        }

        if ($this->can($request, PermissionName::ManageCourses)) {
            $types[] = (new Course)->getMorphClass();
        }

        return $types;
    }

    private function authorizeAnyReviewAccess(Request $request): void
    {
        abort_unless($this->canAny($request, [
            PermissionName::ApproveBloggers,
            PermissionName::ManageBlogs,
            PermissionName::ManageCourses,
            PermissionName::ManageCms,
        ]), 403);
    }

    /**
     * @param  list<PermissionName>  $permissions
     */
    private function canAny(Request $request, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($request, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function can(Request $request, PermissionName $permission): bool
    {
        return $request->user()?->can($permission->value) ?? false;
    }

    private function note(Request $request): ?string
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'review_checklist' => ['nullable', 'array'],
            'review_checklist.*' => ['boolean'],
        ]);

        return $validated['note'] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function section(string $key, string $title, string $description, int $count, array $items): array
    {
        return compact('key', 'title', 'description', 'count', 'items');
    }

    /**
     * @return array{label: string, value: string|null, url: string|null}
     */
    private function meta(string $label, mixed $value, ?string $url = null): array
    {
        return [
            'label' => $label,
            'value' => is_scalar($value) && filled((string) $value) ? (string) $value : null,
            'url' => filled($url) ? $url : null,
        ];
    }

    /**
     * @return array{label: string, url: string, tone: string, method: string}
     */
    private function action(string $label, string $url, string $tone): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'tone' => $tone,
            'method' => 'post',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(Model $record): array
    {
        if (! method_exists($record, 'approvalHistories')) {
            return [];
        }

        $histories = $record->relationLoaded('approvalHistories')
            ? $record->getRelation('approvalHistories')
            : $record->approvalHistories()->with('actor')->latest()->limit(5)->get();

        return $histories
            ->take(5)
            ->map(fn (ApprovalHistory $history): array => [
                'decision' => $history->decision,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'note' => $history->note,
                'actor' => $history->actor?->name,
                'created_at' => $this->dateTimeString($history->created_at),
                'checklist' => $this->historyChecklist($history),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, checked: bool}>
     */
    private function historyChecklist(ApprovalHistory $history): array
    {
        $metadata = $history->getAttribute('metadata');
        $checklist = [];

        if (is_array($metadata)) {
            $candidate = $metadata['review_checklist'] ?? null;
            $checklist = is_array($candidate) ? $candidate : [];
        }

        return collect($checklist)
            ->map(fn (mixed $checked, string|int $key): array => [
                'label' => self::REVIEW_CHECKLIST_LABELS[(string) $key] ?? str_replace('_', ' ', (string) $key),
                'checked' => (bool) $checked,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, from: string|null, to: string|null}>
     */
    private function revisionDiff(EditorialRevision $revision): array
    {
        $content = $revision->editorialable;
        $payload = $revision->getAttribute('payload');

        if (! is_array($payload)) {
            return [];
        }

        if ($content instanceof BlogPost) {
            return $this->modelPayloadDiff($content, $payload, self::BLOG_DIFF_FIELDS);
        }

        if ($content instanceof Course) {
            $coursePayload = is_array($payload['course'] ?? null) ? $payload['course'] : [];

            return [
                ...$this->modelPayloadDiff($content, $coursePayload, self::COURSE_DIFF_FIELDS),
                ...$this->courseOperationDiff($content, $payload),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $fields
     * @return array<int, array{label: string, from: string|null, to: string|null}>
     */
    private function modelPayloadDiff(Model $model, array $payload, array $fields): array
    {
        $diff = [];

        foreach ($fields as $field => $label) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $from = $this->displayValue($model->getAttribute($field));
            $to = $this->displayValue($payload[$field]);

            if ($from === $to) {
                continue;
            }

            $diff[] = compact('label', 'from', 'to');
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, from: string|null, to: string|null}>
     */
    private function courseOperationDiff(Course $course, array $payload): array
    {
        $operations = $payload['operations'] ?? [];

        if (! is_array($operations)) {
            return [];
        }

        return collect($operations)
            ->filter('is_array')
            ->map(fn (array $operation): array => [
                'label' => $this->operationLabel($operation),
                'from' => $this->operationCurrentValue($course, $operation),
                'to' => $this->operationProposedValue($operation),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function operationLabel(array $operation): string
    {
        $target = str_replace('_', ' ', (string) ($operation['target'] ?? 'content'));
        $action = str_replace('_', ' ', (string) ($operation['action'] ?? 'update'));

        return str($target.' '.$action)->headline()->toString();
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function operationCurrentValue(Course $course, array $operation): ?string
    {
        $action = (string) ($operation['action'] ?? '');

        if ($action === 'create') {
            return 'No current item';
        }

        $targetId = isset($operation['target_id']) ? (int) $operation['target_id'] : null;

        if ($targetId === null) {
            return null;
        }

        $record = match ((string) ($operation['target'] ?? '')) {
            'sections' => $course->sections()->whereKey($targetId)->first(),
            'lessons' => $course->lessons()->whereKey($targetId)->first(),
            'resources' => $course->resources()->whereKey($targetId)->first(),
            'faqs' => $course->faqs()->whereKey($targetId)->first(),
            default => null,
        };

        if (! $record instanceof Model) {
            return 'Existing item not found';
        }

        return $this->displayValue(
            $record->getAttribute('title')
                ?? $record->getAttribute('question')
                ?? '#'.$record->getKey(),
        );
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function operationProposedValue(array $operation): ?string
    {
        $action = (string) ($operation['action'] ?? '');

        if ($action === 'delete') {
            return 'Remove from published course';
        }

        $attributes = is_array($operation['attributes'] ?? null) ? $operation['attributes'] : [];

        return $this->displayValue(
            $attributes['title']
                ?? $attributes['question']
                ?? $attributes['description']
                ?? $attributes,
        );
    }

    private function displayValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        if (! is_scalar($value)) {
            return null;
        }

        return str(strip_tags((string) $value))->squish()->limit(220)->toString();
    }

    private function ownershipLabel(Course $course): ?string
    {
        return $course->ownershipVideo?->title
            ?: $course->ownership_video_url
            ?: (filled($course->ownership_statement) ? 'Statement provided' : null);
    }

    private function ownershipUrl(Course $course): ?string
    {
        return $course->ownershipVideo?->url ?: $course->ownership_video_url;
    }

    private function contentLabel(?Model $content): string
    {
        if ($content instanceof BlogPost || $content instanceof Course) {
            return $content->title;
        }

        return 'Unknown content';
    }

    private function contentTypeLabel(?Model $content): string
    {
        return match (true) {
            $content instanceof BlogPost => 'Blog',
            $content instanceof Course => 'Course',
            default => 'Content',
        };
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        if (is_string($value) && filled($value)) {
            return Carbon::parse($value)->toDateTimeString();
        }

        return null;
    }
}
