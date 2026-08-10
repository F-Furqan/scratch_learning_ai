<?php

namespace App\Services\Content;

use App\Enums\CreatorContentDeletionStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Creators\CreatorNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseWorkflowService
{
    /**
     * @var list<string>
     */
    private const COURSE_PAYLOAD_FIELDS = [
        'course_category_id',
        'course_subcategory_id',
        'thumbnail_media_id',
        'title',
        'short_description',
        'description',
        'intro_video_url',
        'level',
        'language',
        'price',
        'is_free',
        'seo_title',
        'seo_description',
    ];

    /**
     * @var list<string>
     */
    private const OWNERSHIP_PAYLOAD_FIELDS = [
        'ownership_video_media_id',
        'ownership_video_url',
        'ownership_statement',
        'ownership_confirmed_at',
    ];

    public function __construct(
        private readonly ApprovalRecorder $approvals,
        private readonly CreatorNotificationService $notifications,
    ) {}

    public function submitForReview(Request $request, Course $course): Course
    {
        $this->ensureReadyForSubmission($course);

        $from = $course->getAttribute('status');
        $course->forceFill([
            'status' => PublishStatus::Pending,
            'published_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->approvals->record(
            $request,
            $course,
            'submitted',
            $from,
            PublishStatus::Pending,
            'Creator submitted course for approval.',
        );

        $this->notifications->courseSubmitted($course);

        return $course;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitPublishedRevision(Request $request, Course $course, User $creator, array $payload): EditorialRevision
    {
        return $this->upsertPublishedRevision(
            $request,
            $course,
            $creator,
            ['course' => Arr::only($payload, self::COURSE_PAYLOAD_FIELDS)],
            'Creator submitted course info changes for review.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitPublishedOwnershipRevision(Request $request, Course $course, User $creator, array $payload): EditorialRevision
    {
        return $this->upsertPublishedRevision(
            $request,
            $course,
            $creator,
            ['course' => Arr::only($payload, self::OWNERSHIP_PAYLOAD_FIELDS)],
            'Creator submitted course ownership proof changes for review.',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submitStructureRevision(
        Request $request,
        Course $course,
        User $creator,
        string $target,
        string $action,
        ?int $targetId,
        array $attributes = [],
    ): EditorialRevision {
        return $this->upsertPublishedRevision(
            $request,
            $course,
            $creator,
            [
                'operation' => [
                    'target' => $target,
                    'action' => $action,
                    'target_id' => $targetId,
                    'attributes' => $attributes,
                ],
            ],
            'Creator submitted course structure changes for review.',
        );
    }

    public function approve(Request $request, Course $course, ?string $note = null): Course
    {
        $this->ensureStatus($course, PublishStatus::Pending, 'Only submitted courses can be approved.');
        $this->ensureReadyForApproval($course);

        /** @var Course $course */
        $course = DB::transaction(function () use ($request, $course, $note): Course {
            $from = $course->getAttribute('status');
            $publishedAt = now();

            $course->forceFill([
                'status' => PublishStatus::Published,
                'published_at' => $publishedAt,
                'admin_notes' => $note ?: $course->admin_notes,
                'rejection_reason' => null,
            ])->save();

            $course->sections()
                ->where('status', '!=', PublishStatus::Published->value)
                ->update([
                    'status' => PublishStatus::Published->value,
                    'published_at' => $publishedAt,
                ]);

            $course->lessons()
                ->where('status', '!=', PublishStatus::Published->value)
                ->update([
                    'status' => PublishStatus::Published->value,
                    'published_at' => $publishedAt,
                    'rejection_reason' => null,
                ]);

            $course->faqs()
                ->where('status', '!=', PublishStatus::Published->value)
                ->update([
                    'status' => PublishStatus::Published->value,
                    'published_at' => $publishedAt,
                ]);

            $this->approvals->record($request, $course, 'approved', $from, PublishStatus::Published, $note, [
                'published_children' => true,
            ]);

            $this->notifications->courseApproved($course, $note);

            return $course;
        });

        return $course;
    }

    public function requestChanges(Request $request, Course $course, ?string $note = null): Course
    {
        $this->ensureStatus($course, PublishStatus::Pending, 'Only submitted courses can receive change requests.');

        $course = $this->transitionCourse(
            $request,
            $course,
            PublishStatus::ChangesRequested,
            'changes_requested',
            $note,
        );

        $this->notifications->courseChangesRequested($course, $note);

        return $course;
    }

    public function reject(Request $request, Course $course, ?string $note = null): Course
    {
        $this->ensureStatus($course, PublishStatus::Pending, 'Only submitted courses can be rejected.');

        $course = $this->rejectLike($request, $course, 'rejected', $note);

        $this->notifications->courseRejected($course, $note);

        return $course;
    }

    public function applyRevision(Request $request, EditorialRevision $revision, ?string $note = null): Course
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted, EditorialRevisionStatus::ChangesRequested], 'Only submitted course revisions can be approved.');

        $course = $this->courseFor($revision);
        $revisionPayload = $revision->getAttribute('payload');
        $payload = is_array($revisionPayload) ? $revisionPayload : [];

        /** @var Course $course */
        $course = DB::transaction(function () use ($request, $revision, $course, $payload, $note): Course {
            $revisionFrom = $revision->getAttribute('status');
            $courseFrom = $course->getAttribute('status');
            $publishedAt = now();
            $coursePayload = $payload['course'] ?? [];

            if (is_array($coursePayload)) {
                $course->fill(Arr::only($coursePayload, [
                    ...self::COURSE_PAYLOAD_FIELDS,
                    ...self::OWNERSHIP_PAYLOAD_FIELDS,
                ]));
            }

            $course->forceFill([
                'status' => PublishStatus::Published,
                'published_at' => $course->getAttribute('published_at') ?: $publishedAt,
                'admin_notes' => $note ?: $course->admin_notes,
                'rejection_reason' => null,
            ])->save();

            foreach ($this->revisionOperations($payload) as $operation) {
                $this->applyCourseOperation($course, $operation, $publishedAt);
            }

            $revision->forceFill([
                'reviewer_id' => $request->user()?->id,
                'status' => EditorialRevisionStatus::Approved,
                'reviewed_at' => now(),
                'published_at' => now(),
            ])->save();

            $this->approvals->record(
                $request,
                $revision,
                'course_revision_approved',
                $revisionFrom,
                EditorialRevisionStatus::Approved,
                $note,
                ['course_id' => $course->id],
            );

            $this->approvals->record(
                $request,
                $course,
                'revision_applied',
                $courseFrom,
                PublishStatus::Published,
                $note,
                ['revision_id' => $revision->id],
            );

            return $course;
        });

        $this->notifications->revisionApproved($revision, $note);

        return $course;
    }

    public function requestRevisionChanges(Request $request, EditorialRevision $revision, ?string $note = null): EditorialRevision
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted], 'Only submitted course revisions can receive change requests.');

        $revision = $this->transitionRevision($request, $revision, EditorialRevisionStatus::ChangesRequested, 'course_revision_changes_requested', $note);

        $this->notifications->revisionChangesRequested($revision, $note);

        return $revision;
    }

    public function rejectRevision(Request $request, EditorialRevision $revision, ?string $note = null): EditorialRevision
    {
        $this->ensureRevisionStatus($revision, [EditorialRevisionStatus::Submitted, EditorialRevisionStatus::ChangesRequested], 'Only active course revisions can be rejected.');

        $revision = $this->transitionRevision($request, $revision, EditorialRevisionStatus::Rejected, 'course_revision_rejected', $note);

        $this->notifications->revisionRejected($revision, $note);

        return $revision;
    }

    public function requestDeletion(Request $request, Course $course, User $creator, ?string $reason = null): CreatorContentDeletionRequest
    {
        $deletionRequest = CreatorContentDeletionRequest::query()->firstOrCreate(
            [
                'requester_id' => $creator->id,
                'content_type' => $course->getMorphClass(),
                'content_id' => $course->id,
                'status' => CreatorContentDeletionStatus::Pending,
            ],
            [
                'reason' => $reason ?: 'Creator requested deletion.',
            ],
        );

        if ($this->statusValue($course->getAttribute('status')) !== PublishStatus::DeleteRequested->value) {
            $this->transitionCourse(
                $request,
                $course,
                PublishStatus::DeleteRequested,
                'delete_requested',
                $reason ?: 'Creator requested deletion.',
            );
        }

        return $deletionRequest;
    }

    public function approveDeletionRequest(Request $request, CreatorContentDeletionRequest $deletionRequest, ?string $note = null): CreatorContentDeletionRequest
    {
        return DB::transaction(function () use ($request, $deletionRequest, $note): CreatorContentDeletionRequest {
            $this->ensureDeletionRequestPending($deletionRequest);

            $content = $deletionRequest->content;
            $from = $deletionRequest->getAttribute('status');

            $deletionRequest->forceFill([
                'status' => CreatorContentDeletionStatus::Approved,
                'decided_by' => $request->user()?->id,
                'decided_at' => now(),
                'admin_note' => $note,
            ])->save();

            if ($content instanceof Course) {
                $this->transitionCourse($request, $content, PublishStatus::Trashed, 'trashed', $note);
                $content->delete();
            }

            $this->approvals->record(
                $request,
                $deletionRequest,
                'delete_approved',
                $from,
                CreatorContentDeletionStatus::Approved,
                $note,
                $this->deletionRequestMetadata($deletionRequest),
            );

            $this->notifications->deletionApproved($deletionRequest, $content, $note);

            return $deletionRequest;
        });
    }

    public function rejectDeletionRequest(Request $request, CreatorContentDeletionRequest $deletionRequest, ?string $note = null): CreatorContentDeletionRequest
    {
        return DB::transaction(function () use ($request, $deletionRequest, $note): CreatorContentDeletionRequest {
            $this->ensureDeletionRequestPending($deletionRequest);

            $content = $deletionRequest->content;
            $from = $deletionRequest->getAttribute('status');

            $deletionRequest->forceFill([
                'status' => CreatorContentDeletionStatus::Rejected,
                'decided_by' => $request->user()?->id,
                'decided_at' => now(),
                'admin_note' => $note,
            ])->save();

            if ($content instanceof Course && $this->statusValue($content->getAttribute('status')) === PublishStatus::DeleteRequested->value) {
                $this->transitionCourse($request, $content, PublishStatus::Published, 'delete_rejected', $note);
            }

            $this->approvals->record(
                $request,
                $deletionRequest,
                'delete_rejected',
                $from,
                CreatorContentDeletionStatus::Rejected,
                $note,
                $this->deletionRequestMetadata($deletionRequest),
            );

            $this->notifications->deletionRejected($deletionRequest, $content, $note);

            return $deletionRequest;
        });
    }

    /**
     * @return list<string>
     */
    public function readinessIssues(Course $course, bool $requireCurriculum = true): array
    {
        $course->loadMissing(['sections', 'lessons', 'creator.bloggerProfile']);

        $issues = [];

        if (blank($course->title) || blank($course->description)) {
            $issues[] = 'Complete the course title and description.';
        }

        if (blank($course->ownership_statement)) {
            $issues[] = 'Add the ownership confirmation statement.';
        }

        if (blank($course->ownership_video_url) && blank($course->ownership_video_media_id)) {
            $issues[] = 'Upload or link the ownership confirmation video.';
        }

        if ($requireCurriculum && $course->sections->isEmpty()) {
            $issues[] = 'Add at least one course section.';
        }

        if ($requireCurriculum && $course->lessons->isEmpty()) {
            $issues[] = 'Add at least one lesson.';
        }

        if ($course->creator?->bloggerProfile === null) {
            $issues[] = 'Creator profile is missing.';
        }

        return $issues;
    }

    public function hasOwnershipProof(Course $course): bool
    {
        return blank($course->ownership_statement) === false
            && (blank($course->ownership_video_url) === false || blank($course->ownership_video_media_id) === false);
    }

    private function ensureReadyForSubmission(Course $course): void
    {
        $this->throwIfIssues($this->readinessIssues($course));
    }

    private function ensureReadyForApproval(Course $course): void
    {
        $this->throwIfIssues($this->readinessIssues($course));
    }

    /**
     * @param  list<string>  $issues
     */
    private function throwIfIssues(array $issues): void
    {
        if ($issues !== []) {
            throw ValidationException::withMessages([
                'course' => implode(' ', $issues),
            ]);
        }
    }

    private function rejectLike(Request $request, Course $course, string $decision, ?string $note): Course
    {
        $from = $course->getAttribute('status');

        $course->forceFill([
            'status' => PublishStatus::Rejected,
            'published_at' => null,
            'admin_notes' => $note ?: $course->admin_notes,
            'rejection_reason' => $note,
        ])->save();

        $this->approvals->record($request, $course, $decision, $from, PublishStatus::Rejected, $note);

        return $course;
    }

    private function transitionCourse(Request $request, Course $course, PublishStatus $to, string $decision, ?string $note = null): Course
    {
        $from = $course->getAttribute('status');

        $course->forceFill([
            'status' => $to,
            'published_at' => $to === PublishStatus::Published ? ($course->getAttribute('published_at') ?: now()) : null,
            'admin_notes' => filled($note) ? $note : $course->admin_notes,
            'rejection_reason' => in_array($to, [PublishStatus::ChangesRequested, PublishStatus::Rejected], true) ? $note : null,
        ])->save();

        $this->approvals->record($request, $course, $decision, $from, $to, $note);

        return $course;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function upsertPublishedRevision(Request $request, Course $course, User $creator, array $changes, string $note): EditorialRevision
    {
        $revision = $this->activeRevisionFor($course);
        $from = $revision?->getAttribute('status');
        $currentPayload = $revision?->getAttribute('payload');
        $payload = is_array($currentPayload) ? $currentPayload : [];

        if (isset($changes['course']) && is_array($changes['course'])) {
            $payload['course'] = [
                ...(is_array($payload['course'] ?? null) ? $payload['course'] : []),
                ...$changes['course'],
            ];
        }

        if (isset($changes['operation']) && is_array($changes['operation'])) {
            $payload = $this->appendOperation($payload, $changes['operation']);
        }

        $attributes = [
            'author_id' => $creator->id,
            'reviewer_id' => null,
            'title' => 'Course revision: '.$course->title,
            'summary' => $this->revisionSummary($payload),
            'payload' => $payload,
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'published_at' => null,
        ];

        if ($revision instanceof EditorialRevision) {
            $revision->fill($attributes)->save();
        } else {
            $revision = $course->editorialRevisions()->create($attributes);
        }

        $this->approvals->record(
            $request,
            $revision,
            'course_revision_submitted',
            $from,
            EditorialRevisionStatus::Submitted,
            $note,
            ['course_id' => $course->id],
        );

        return $revision;
    }

    private function activeRevisionFor(Course $course): ?EditorialRevision
    {
        return $course->editorialRevisions()
            ->whereIn('status', [
                EditorialRevisionStatus::Submitted->value,
                EditorialRevisionStatus::ChangesRequested->value,
            ])
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function appendOperation(array $payload, array $operation): array
    {
        $target = (string) ($operation['target'] ?? '');
        $targetId = $operation['target_id'] ?? null;
        $operations = $this->revisionOperations($payload);

        if ($target !== '' && $targetId !== null) {
            $operations = array_values(array_filter(
                $operations,
                fn (array $existing): bool => ! (
                    ($existing['target'] ?? null) === $target
                    && (string) ($existing['target_id'] ?? '') === (string) $targetId
                ),
            ));
        }

        $operation['submitted_at'] = now()->toISOString();
        $operations[] = $operation;
        $payload['operations'] = $operations;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function revisionOperations(array $payload): array
    {
        $operations = $payload['operations'] ?? [];

        if (! is_array($operations)) {
            return [];
        }

        return array_values(array_filter($operations, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function applyCourseOperation(Course $course, array $operation, mixed $publishedAt): void
    {
        $target = (string) ($operation['target'] ?? '');
        $action = (string) ($operation['action'] ?? '');
        $targetId = isset($operation['target_id']) ? (int) $operation['target_id'] : null;
        $attributes = is_array($operation['attributes'] ?? null) ? $operation['attributes'] : [];

        match ($target) {
            'sections' => $this->applySectionOperation($course, $action, $targetId, $attributes, $publishedAt),
            'lessons' => $this->applyLessonOperation($course, $action, $targetId, $attributes, $publishedAt),
            'resources' => $this->applyResourceOperation($course, $action, $targetId, $attributes),
            'faqs' => $this->applyFaqOperation($course, $action, $targetId, $attributes, $publishedAt),
            default => abort(422, 'Unsupported course revision target.'),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applySectionOperation(Course $course, string $action, ?int $targetId, array $attributes, mixed $publishedAt): void
    {
        if ($action === 'create') {
            $course->sections()->create(Arr::only($attributes, ['title', 'description', 'sort_order']) + [
                'status' => PublishStatus::Published,
                'published_at' => $publishedAt,
            ]);

            return;
        }

        $section = $this->sectionFor($course, $targetId);

        if ($action === 'delete') {
            $section->delete();

            return;
        }

        if ($action === 'update') {
            $section->fill(Arr::only($attributes, ['title', 'description', 'sort_order']))->save();

            return;
        }

        abort(422, 'Unsupported section revision action.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyLessonOperation(Course $course, string $action, ?int $targetId, array $attributes, mixed $publishedAt): void
    {
        $fillable = ['course_section_id', 'title', 'order_number', 'content', 'video_type', 'video_url', 'video_file_id', 'is_free', 'is_paid', 'preview_word_limit'];

        if ($action === 'create') {
            $this->ensureOptionalSectionBelongsToCourse($course, $attributes['course_section_id'] ?? null);
            $course->lessons()->create(Arr::only($attributes, $fillable) + [
                'status' => PublishStatus::Published,
                'published_at' => $publishedAt,
            ]);

            return;
        }

        $lesson = $this->lessonFor($course, $targetId);

        if ($action === 'delete') {
            $lesson->delete();

            return;
        }

        if ($action === 'update') {
            $this->ensureOptionalSectionBelongsToCourse($course, $attributes['course_section_id'] ?? null);
            $lesson->fill(Arr::only($attributes, $fillable))->save();

            return;
        }

        abort(422, 'Unsupported lesson revision action.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyResourceOperation(Course $course, string $action, ?int $targetId, array $attributes): void
    {
        $fillable = ['course_lesson_id', 'media_asset_id', 'title', 'description', 'type', 'access_level', 'file_path', 'external_url', 'is_downloadable', 'is_active', 'sort_order'];

        if ($action === 'create') {
            $this->ensureOptionalLessonBelongsToCourse($course, $attributes['course_lesson_id'] ?? null);
            $course->resources()->create(Arr::only($attributes, $fillable));

            return;
        }

        $resource = $this->resourceFor($course, $targetId);

        if ($action === 'delete') {
            $resource->delete();

            return;
        }

        if ($action === 'update') {
            $this->ensureOptionalLessonBelongsToCourse($course, $attributes['course_lesson_id'] ?? null);
            $resource->fill(Arr::only($attributes, $fillable))->save();

            return;
        }

        abort(422, 'Unsupported resource revision action.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyFaqOperation(Course $course, string $action, ?int $targetId, array $attributes, mixed $publishedAt): void
    {
        $fillable = ['course_lesson_id', 'question', 'answer', 'sort_order'];

        if ($action === 'create') {
            $this->ensureOptionalLessonBelongsToCourse($course, $attributes['course_lesson_id'] ?? null);
            $course->faqs()->create(Arr::only($attributes, $fillable) + [
                'status' => PublishStatus::Published,
                'published_at' => $publishedAt,
            ]);

            return;
        }

        $faq = $this->faqFor($course, $targetId);

        if ($action === 'delete') {
            $faq->delete();

            return;
        }

        if ($action === 'update') {
            $this->ensureOptionalLessonBelongsToCourse($course, $attributes['course_lesson_id'] ?? null);
            $faq->fill(Arr::only($attributes, $fillable))->save();

            return;
        }

        abort(422, 'Unsupported FAQ revision action.');
    }

    private function sectionFor(Course $course, ?int $targetId): CourseSection
    {
        abort_if($targetId === null, 422, 'Missing section revision target.');

        return $course->sections()->whereKey($targetId)->firstOrFail();
    }

    private function lessonFor(Course $course, ?int $targetId): CourseLesson
    {
        abort_if($targetId === null, 422, 'Missing lesson revision target.');

        return $course->lessons()->whereKey($targetId)->firstOrFail();
    }

    private function resourceFor(Course $course, ?int $targetId): CourseResource
    {
        abort_if($targetId === null, 422, 'Missing resource revision target.');

        return $course->resources()->whereKey($targetId)->firstOrFail();
    }

    private function faqFor(Course $course, ?int $targetId): CourseFaq
    {
        abort_if($targetId === null, 422, 'Missing FAQ revision target.');

        return $course->faqs()->whereKey($targetId)->firstOrFail();
    }

    private function ensureOptionalSectionBelongsToCourse(Course $course, mixed $sectionId): void
    {
        if (blank($sectionId)) {
            return;
        }

        abort_unless($course->sections()->whereKey((int) $sectionId)->exists(), 422, 'The selected section does not belong to this course.');
    }

    private function ensureOptionalLessonBelongsToCourse(Course $course, mixed $lessonId): void
    {
        if (blank($lessonId)) {
            return;
        }

        abort_unless($course->lessons()->whereKey((int) $lessonId)->exists(), 422, 'The selected lesson does not belong to this course.');
    }

    private function transitionRevision(Request $request, EditorialRevision $revision, EditorialRevisionStatus $to, string $decision, ?string $note = null): EditorialRevision
    {
        $from = $revision->getAttribute('status');
        $revisionPayload = $revision->getAttribute('payload');
        $payload = is_array($revisionPayload) ? $revisionPayload : [];

        if (filled($note)) {
            $payload['review_note'] = $note;
        }

        $revision->forceFill([
            'reviewer_id' => $request->user()?->id,
            'status' => $to,
            'payload' => $payload,
            'reviewed_at' => now(),
        ])->save();

        $this->approvals->record(
            $request,
            $revision,
            $decision,
            $from,
            $to,
            $note,
            ['course_id' => $this->courseFor($revision)->id],
        );

        return $revision;
    }

    private function courseFor(EditorialRevision $revision): Course
    {
        $content = $revision->editorialable;

        abort_unless($content instanceof Course, 422, 'This revision is not linked to a course.');

        return $content;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function revisionSummary(array $payload): string
    {
        $parts = [];

        if (is_array($payload['course'] ?? null)) {
            $parts[] = 'course info';
        }

        $operationCount = count($this->revisionOperations($payload));

        if ($operationCount > 0) {
            $parts[] = $operationCount.' curriculum change'.($operationCount === 1 ? '' : 's');
        }

        return $parts === [] ? 'Course revision' : 'Updates: '.implode(', ', $parts);
    }

    /**
     * @param  list<EditorialRevisionStatus>  $allowed
     */
    private function ensureRevisionStatus(EditorialRevision $revision, array $allowed, string $message): void
    {
        $status = $revision->getAttribute('status');

        foreach ($allowed as $allowedStatus) {
            if ($status === $allowedStatus || $status === $allowedStatus->value) {
                return;
            }
        }

        abort(422, $message);
    }

    private function ensureStatus(Course $course, PublishStatus $status, string $message): void
    {
        $actual = $course->getAttribute('status');

        if ($actual === $status || $actual === $status->value) {
            return;
        }

        abort(422, $message);
    }

    private function ensureDeletionRequestPending(CreatorContentDeletionRequest $deletionRequest): void
    {
        abort_unless(
            $this->statusValue($deletionRequest->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value,
            422,
            'Only pending deletion requests can be decided.',
        );
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function deletionRequestMetadata(CreatorContentDeletionRequest $deletionRequest): array
    {
        return [
            'content_type' => $deletionRequest->content_type,
            'content_id' => $deletionRequest->content_id,
            'requester_id' => $deletionRequest->requester_id,
        ];
    }
}
