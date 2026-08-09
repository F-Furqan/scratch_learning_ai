<?php

namespace App\Http\Controllers\Creator;

use App\Enums\CreatorContentDeletionStatus;
use App\Enums\LearningResourceAccess;
use App\Enums\PublishStatus;
use App\Enums\VideoType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Content\CourseWorkflowService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreatorCourseController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->string('status')->toString();

        $courses = Course::query()
            ->where('created_by', $user->id)
            ->with([
                'category',
                'subcategory',
                'approvalHistories.actor',
                'deletionRequests',
                'activeEditorialRevision.approvalHistories.actor',
                'latestEditorialRevision.approvalHistories.actor',
            ])
            ->withCount(['sections', 'lessons', 'resources', 'faqs', 'enrollments'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('creator/ContentIndex', [
            'kind' => 'course',
            'title' => 'Creator Courses',
            'description' => 'Prepare course drafts, request review, and monitor publishing decisions.',
            'create_url' => route('creator.courses.create', absolute: false),
            'filters' => ['status' => $status],
            'status_options' => $this->courseStatusOptions(),
            'summary' => $this->summary($user),
            'items' => $courses->through(fn (Course $course): array => $this->coursePayload($course, $user)),
            'can_create' => $user->can('create', Course::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('create', Course::class);

        return Inertia::render('creator/ContentForm', [
            'kind' => 'course',
            'title' => 'New Course Draft',
            'item' => null,
            'categories' => $this->courseCategories(),
            'subcategories' => $this->courseSubcategories(),
            'store_url' => route('creator.courses.store', absolute: false),
            'cancel_url' => route('creator.courses.index', absolute: false),
        ]);
    }

    public function store(Request $request, ApprovalRecorder $approvals): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('create', Course::class);

        $validated = $this->validateCourse($request);

        $course = Course::query()->create([
            ...$validated,
            'created_by' => $user->id,
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        $approvals->record($request, $course, 'draft_created', null, PublishStatus::Draft, 'Creator created course draft.');

        return redirect()->route('creator.courses.edit', $course);
    }

    public function edit(Request $request, Course $course, CourseWorkflowService $workflow): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('update', $course) || $user->can('requestRevision', $course), 403);

        $course->load([
            'category',
            'subcategory',
            'thumbnail',
            'ownershipVideo',
            'approvalHistories.actor',
            'deletionRequests',
            'sections.lessons',
            'lessons.section',
            'lessons.videoFile',
            'resources.lesson',
            'resources.mediaAsset',
            'faqs.lesson',
            'activeEditorialRevision.approvalHistories.actor',
            'latestEditorialRevision.approvalHistories.actor',
        ])->loadCount(['sections', 'lessons', 'resources', 'faqs', 'enrollments']);

        return Inertia::render('creator/CourseBuilder', [
            'kind' => 'course',
            'title' => $user->can('requestRevision', $course) ? 'Submit Course Revision' : 'Course Builder',
            'item' => $this->coursePayload($course, $user),
            'builder' => $this->builderPayload($course, $workflow),
            'categories' => $this->courseCategories(),
            'subcategories' => $this->courseSubcategories(),
            'media_assets' => $this->mediaPickerOptions(),
            'video_types' => $this->enumOptions(VideoType::cases()),
            'resource_access_options' => $this->enumOptions(LearningResourceAccess::cases()),
            'store_url' => route('creator.courses.store', absolute: false),
            'update_url' => route('creator.courses.update', $course, absolute: false),
            'submit_url' => route('creator.courses.submit', $course, absolute: false),
            'delete_request_url' => route('creator.courses.delete-request', $course, absolute: false),
            'ownership_update_url' => route('creator.courses.ownership.update', $course, absolute: false),
            'section_store_url' => route('creator.courses.sections.store', $course, absolute: false),
            'lesson_store_url' => route('creator.courses.lessons.store', $course, absolute: false),
            'resource_store_url' => route('creator.courses.resources.store', $course, absolute: false),
            'faq_store_url' => route('creator.courses.faqs.store', $course, absolute: false),
            'cancel_url' => route('creator.courses.index', absolute: false),
        ]);
    }

    public function update(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $this->validateCourse($request);

        if ($this->isPublished($course)) {
            $user = $this->authorizeCourseRevision($request, $course);
            $this->recordCopyrightDeclaration($request, $course);
            $workflow->submitPublishedRevision($request, $course, $user, $validated);

            return back()->with('status', 'creator-course-revision-submitted');
        }

        Gate::forUser($request->user())->authorize('update', $course);

        $course->fill($validated);
        $course->save();

        return back()->with('status', 'creator-course-updated');
    }

    public function submit(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('submit', $course);

        $this->recordCopyrightDeclaration($request, $course);
        $workflow->submitForReview($request, $course);

        return redirect()->route('creator.courses.index', ['status' => PublishStatus::Pending->value]);
    }

    public function updateOwnership(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'ownership_video_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'ownership_video_url' => ['nullable', 'url', 'max:2048'],
            'ownership_statement' => ['required', 'string', 'max:2000'],
        ]);

        if (blank($validated['ownership_video_media_id'] ?? null) && blank($validated['ownership_video_url'] ?? null)) {
            return back()
                ->withErrors(['ownership_video_url' => 'Upload or link the ownership confirmation video.'])
                ->withInput();
        }

        if ($this->isPublished($course)) {
            $user = $this->authorizeCourseRevision($request, $course);
            $this->recordCopyrightDeclaration($request, $course);
            $workflow->submitPublishedOwnershipRevision($request, $course, $user, [
                ...$validated,
                'ownership_confirmed_at' => now(),
            ]);

            return back()->with('status', 'creator-course-revision-submitted');
        }

        $this->authorizeCourseMutation($request, $course);

        $course->forceFill([
            ...$validated,
            'ownership_confirmed_at' => now(),
        ])->save();

        return back()->with('status', 'creator-course-ownership-updated');
    }

    public function storeSection(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $this->validatedSection($request);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'sections', 'create', null, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $course->sections()->create($validated + [
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        return back()->with('status', 'creator-course-section-created');
    }

    public function updateSection(Request $request, CourseSection $section, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $section->course()->firstOrFail();
        $validated = $this->validatedSection($request);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'sections', 'update', $section->id, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $section->fill($validated)->save();

        return back()->with('status', 'creator-course-section-updated');
    }

    public function destroySection(Request $request, CourseSection $section, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $section->course()->firstOrFail();

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'sections', 'delete', $section->id);
        }

        $this->authorizeCourseMutation($request, $course);

        $section->delete();

        return back()->with('status', 'creator-course-section-deleted');
    }

    public function storeLesson(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $this->validatedLesson($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'lessons', 'create', null, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $course->lessons()->create($validated + [
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        return back()->with('status', 'creator-course-lesson-created');
    }

    public function updateLesson(Request $request, CourseLesson $lesson, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $lesson->course()->firstOrFail();
        $validated = $this->validatedLesson($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'lessons', 'update', $lesson->id, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $lesson->fill($validated)->save();

        return back()->with('status', 'creator-course-lesson-updated');
    }

    public function destroyLesson(Request $request, CourseLesson $lesson, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $lesson->course()->firstOrFail();

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'lessons', 'delete', $lesson->id);
        }

        $this->authorizeCourseMutation($request, $course);

        $lesson->delete();

        return back()->with('status', 'creator-course-lesson-deleted');
    }

    public function storeResource(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $this->validatedResource($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'resources', 'create', null, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $course->resources()->create($validated);

        return back()->with('status', 'creator-course-resource-created');
    }

    public function updateResource(Request $request, CourseResource $resource, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $resource->course()->firstOrFail();
        $validated = $this->validatedResource($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'resources', 'update', $resource->id, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $resource->fill($validated)->save();

        return back()->with('status', 'creator-course-resource-updated');
    }

    public function destroyResource(Request $request, CourseResource $resource, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $resource->course()->firstOrFail();

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'resources', 'delete', $resource->id);
        }

        $this->authorizeCourseMutation($request, $course);

        $resource->delete();

        return back()->with('status', 'creator-course-resource-deleted');
    }

    public function storeFaq(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        $validated = $this->validatedFaq($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'faqs', 'create', null, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $course->faqs()->create($validated + [
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        return back()->with('status', 'creator-course-faq-created');
    }

    public function updateFaq(Request $request, CourseFaq $faq, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $faq->course()->firstOrFail();
        $validated = $this->validatedFaq($request, $course);

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'faqs', 'update', $faq->id, $validated);
        }

        $this->authorizeCourseMutation($request, $course);

        $faq->fill($validated)->save();

        return back()->with('status', 'creator-course-faq-updated');
    }

    public function destroyFaq(Request $request, CourseFaq $faq, CourseWorkflowService $workflow): RedirectResponse
    {
        $course = $faq->course()->firstOrFail();

        if ($this->isPublished($course)) {
            return $this->submitPublishedStructureRevision($request, $workflow, $course, 'faqs', 'delete', $faq->id);
        }

        $this->authorizeCourseMutation($request, $course);

        $faq->delete();

        return back()->with('status', 'creator-course-faq-deleted');
    }

    public function requestDelete(Request $request, Course $course, CourseWorkflowService $workflow): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('requestDelete', $course);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $workflow->requestDeletion($request, $course, $user, $validated['reason'] ?? null);

        return back()->with('status', 'creator-delete-requested');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $counts = Course::query()
            ->where('created_by', $user->id)
            ->get(['status'])
            ->map(fn (Course $course): string => $this->enumValue($course->getAttribute('status')) ?? 'unknown')
            ->countBy();

        return [
            'drafts' => (int) ($counts[PublishStatus::Draft->value] ?? 0),
            'pending_approval' => (int) ($counts[PublishStatus::Pending->value] ?? 0),
            'published' => (int) ($counts[PublishStatus::Published->value] ?? 0),
            'changes_requested' => (int) ($counts[PublishStatus::Rejected->value] ?? 0),
            'delete_requests' => CreatorContentDeletionRequest::query()
                ->where('requester_id', $user->id)
                ->where('status', CreatorContentDeletionStatus::Pending->value)
                ->where('content_type', (new Course)->getMorphClass())
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courseCategories(): array
    {
        return CourseCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CourseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courseSubcategories(): array
    {
        return CourseSubcategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'course_category_id'])
            ->map(fn (CourseSubcategory $subcategory): array => [
                'id' => $subcategory->id,
                'course_category_id' => $subcategory->course_category_id,
                'name' => $subcategory->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course, User $user): array
    {
        $latestHistory = $course->approvalHistories->first();
        $activeRevision = $course->activeEditorialRevision;
        $latestRevision = $activeRevision ?: $course->latestEditorialRevision;
        $pendingDeletion = $course->deletionRequests
            ->first(fn (CreatorContentDeletionRequest $request): bool => $this->enumValue($request->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'category_id' => $course->course_category_id,
            'subcategory_id' => $course->course_subcategory_id,
            'category' => $course->category?->name,
            'subcategory' => $course->subcategory?->name,
            'short_description' => $course->short_description,
            'description' => $course->description,
            'intro_video_url' => $course->intro_video_url,
            'thumbnail_media_id' => $course->thumbnail_media_id,
            'thumbnail' => $course->thumbnail ? [
                'id' => $course->thumbnail->id,
                'url' => $course->thumbnail->url,
                'title' => $course->thumbnail->title,
            ] : null,
            'level' => $course->level,
            'language' => $course->language,
            'price' => (string) $course->price,
            'is_free' => (bool) $course->is_free,
            'seo_title' => $course->getAttribute('seo_title'),
            'seo_description' => $course->getAttribute('seo_description'),
            'ownership_video_media_id' => $course->ownership_video_media_id,
            'ownership_video_url' => $course->ownership_video_url,
            'ownership_statement' => $course->ownership_statement,
            'ownership_confirmed_at' => $this->dateString($course->getAttribute('ownership_confirmed_at')),
            'ownership_complete' => blank($course->ownership_statement) === false
                && (blank($course->ownership_video_url) === false || blank($course->ownership_video_media_id) === false),
            'status' => $this->enumValue($course->getAttribute('status')),
            'published_at' => $this->dateString($course->getAttribute('published_at')),
            'updated_at' => $this->dateString($course->getAttribute('updated_at')),
            'rejection_reason' => $course->rejection_reason,
            'admin_notes' => $course->admin_notes,
            'engagement_label' => $course->lessons_count.' lessons / '.$course->enrollments_count.' enrollments',
            'edit_url' => $user->can('update', $course) || $user->can('requestRevision', $course) ? route('creator.courses.edit', $course, absolute: false) : null,
            'submit_url' => $user->can('submit', $course) ? route('creator.courses.submit', $course, absolute: false) : null,
            'delete_request_url' => $user->can('requestDelete', $course) ? route('creator.courses.delete-request', $course, absolute: false) : null,
            'public_url' => $course->isPublished() ? route('public.courses.show', $course->slug, absolute: false) : null,
            'active_revision' => $latestRevision instanceof EditorialRevision ? [
                'id' => $latestRevision->id,
                'status' => $this->enumValue($latestRevision->getAttribute('status')),
                'title' => $latestRevision->title,
                'review_note' => $this->revisionReviewNote($latestRevision),
                'submitted_at' => $this->dateString($latestRevision->getAttribute('submitted_at')),
                'reviewed_at' => $this->dateString($latestRevision->getAttribute('reviewed_at')),
                'latest_approval' => $latestRevision->approvalHistories->first() ? [
                    'decision' => $latestRevision->approvalHistories->first()->decision,
                    'note' => $latestRevision->approvalHistories->first()->note,
                    'actor' => $latestRevision->approvalHistories->first()->actor?->name,
                    'created_at' => $this->dateString($latestRevision->approvalHistories->first()->created_at),
                ] : null,
            ] : null,
            'pending_delete_request' => $pendingDeletion ? [
                'id' => $pendingDeletion->id,
                'reason' => $pendingDeletion->reason,
                'status' => $this->enumValue($pendingDeletion->getAttribute('status')),
            ] : null,
            'latest_approval' => $latestHistory ? [
                'decision' => $latestHistory->decision,
                'from_status' => $latestHistory->from_status,
                'to_status' => $latestHistory->to_status,
                'note' => $latestHistory->note,
                'actor' => $latestHistory->actor?->name,
                'created_at' => $this->dateString($latestHistory->created_at),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourse(Request $request): array
    {
        $validated = $request->validate([
            'course_category_id' => ['nullable', Rule::exists('course_categories', 'id')],
            'course_subcategory_id' => ['nullable', Rule::exists('course_subcategories', 'id')],
            'thumbnail_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string'],
            'intro_video_url' => ['nullable', 'url', 'max:2048'],
            'level' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:16'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'is_free' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_free'] = (bool) ($validated['is_free'] ?? false);
        $validated['price'] = $validated['is_free'] ? 0 : ($validated['price'] ?? 0);
        $validated['language'] = ($validated['language'] ?? null) ?: 'en';

        return $validated;
    }

    private function recordCopyrightDeclaration(Request $request, Course $course): void
    {
        $request->validate([
            'copyright_declaration_accepted' => ['accepted'],
        ]);

        $course->forceFill([
            'copyright_declaration_accepted_at' => now(),
            'copyright_declaration_ip' => $request->ip(),
            'copyright_declaration_user_agent' => str($request->userAgent() ?? '')->limit(2000, '')->toString(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function builderPayload(Course $course, CourseWorkflowService $workflow): array
    {
        return [
            'readiness_issues' => $workflow->readinessIssues($course),
            'sections' => $course->sections
                ->sortBy('sort_order')
                ->map(fn (CourseSection $section): array => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'status' => $this->enumValue($section->getAttribute('status')),
                    'update_url' => route('creator.course-sections.update', $section, absolute: false),
                    'delete_url' => route('creator.course-sections.destroy', $section, absolute: false),
                ])->values()->all(),
            'lessons' => $course->lessons
                ->sortBy('order_number')
                ->map(fn (CourseLesson $lesson): array => [
                    'id' => $lesson->id,
                    'course_section_id' => $lesson->course_section_id,
                    'section_title' => $lesson->section?->title,
                    'title' => $lesson->title,
                    'order_number' => $lesson->order_number,
                    'content' => $lesson->content,
                    'video_type' => $this->enumValue($lesson->getAttribute('video_type')),
                    'video_url' => $lesson->video_url,
                    'video_file_id' => $lesson->video_file_id,
                    'is_free' => (bool) $lesson->is_free,
                    'is_paid' => (bool) $lesson->is_paid,
                    'preview_word_limit' => $lesson->preview_word_limit,
                    'status' => $this->enumValue($lesson->getAttribute('status')),
                    'update_url' => route('creator.course-lessons.update', $lesson, absolute: false),
                    'delete_url' => route('creator.course-lessons.destroy', $lesson, absolute: false),
                ])->values()->all(),
            'resources' => $course->resources
                ->sortBy('sort_order')
                ->map(fn (CourseResource $resource): array => [
                    'id' => $resource->id,
                    'course_lesson_id' => $resource->course_lesson_id,
                    'lesson_title' => $resource->lesson?->title,
                    'media_asset_id' => $resource->media_asset_id,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'type' => $resource->type,
                    'access_level' => $this->enumValue($resource->getAttribute('access_level')),
                    'file_path' => $resource->file_path,
                    'external_url' => $resource->external_url,
                    'is_downloadable' => (bool) $resource->is_downloadable,
                    'is_active' => (bool) $resource->is_active,
                    'sort_order' => $resource->sort_order,
                    'update_url' => route('creator.course-resources.update', $resource, absolute: false),
                    'delete_url' => route('creator.course-resources.destroy', $resource, absolute: false),
                ])->values()->all(),
            'faqs' => $course->faqs
                ->sortBy('sort_order')
                ->map(fn (CourseFaq $faq): array => [
                    'id' => $faq->id,
                    'course_lesson_id' => $faq->course_lesson_id,
                    'lesson_title' => $faq->lesson?->title,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                    'status' => $this->enumValue($faq->getAttribute('status')),
                    'update_url' => route('creator.course-faqs.update', $faq, absolute: false),
                    'delete_url' => route('creator.course-faqs.destroy', $faq, absolute: false),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSection(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedLesson(Request $request, Course $course): array
    {
        $validated = $request->validate([
            'course_section_id' => ['nullable', Rule::exists('course_sections', 'id')->where('course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'order_number' => ['nullable', 'integer', 'min:0'],
            'content' => ['nullable', 'string'],
            'video_type' => ['required', Rule::in($this->enumValues(VideoType::cases()))],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'video_file_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'is_free' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'preview_word_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_free'] = (bool) ($validated['is_free'] ?? false);
        $validated['is_paid'] = (bool) ($validated['is_paid'] ?? ! $validated['is_free']);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedResource(Request $request, Course $course): array
    {
        $validated = $request->validate([
            'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $course->id)],
            'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:40'],
            'access_level' => ['required', Rule::in($this->enumValues(LearningResourceAccess::cases()))],
            'file_path' => ['nullable', 'string', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'is_downloadable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['type'] = ($validated['type'] ?? null) ?: 'download';
        $validated['is_downloadable'] = (bool) ($validated['is_downloadable'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFaq(Request $request, Course $course): array
    {
        return $request->validate([
            'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $course->id)],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function authorizeCourseMutation(Request $request, Course $course): void
    {
        Gate::forUser($request->user())->authorize('update', $course);
    }

    private function authorizeCourseRevision(Request $request, Course $course): User
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('requestRevision', $course);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submitPublishedStructureRevision(
        Request $request,
        CourseWorkflowService $workflow,
        Course $course,
        string $target,
        string $action,
        ?int $targetId,
        array $attributes = [],
    ): RedirectResponse {
        $user = $this->authorizeCourseRevision($request, $course);

        $workflow->submitStructureRevision($request, $course, $user, $target, $action, $targetId, $attributes);

        return back()->with('status', 'creator-course-revision-submitted');
    }

    private function isPublished(Course $course): bool
    {
        return $this->enumValue($course->getAttribute('status')) === PublishStatus::Published->value;
    }

    private function revisionReviewNote(EditorialRevision $revision): ?string
    {
        $payload = $revision->getAttribute('payload');

        return is_array($payload) && is_string($payload['review_note'] ?? null)
            ? $payload['review_note']
            : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function courseStatusOptions(): array
    {
        return collect([
            PublishStatus::Draft,
            PublishStatus::Pending,
            PublishStatus::Published,
            PublishStatus::Rejected,
            PublishStatus::Archived,
        ])->map(fn (PublishStatus $status): array => [
            'label' => $status->label(),
            'value' => $status->value,
        ])->values()->all();
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<int, array{label: string, value: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => [
            'label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(),
            'value' => (string) $case->value,
        ], $cases);
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_values(array_map(fn (BackedEnum $case): string => (string) $case->value, $cases));
    }

    /**
     * @return array<int, array{id: int, name: string, url: string|null}>
     */
    private function mediaPickerOptions(): array
    {
        return MediaAsset::query()
            ->latest()
            ->limit(100)
            ->get(['id', 'title', 'path', 'url'])
            ->map(fn (MediaAsset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->title ?: basename($asset->path),
                'url' => $asset->url,
            ])
            ->values()
            ->all();
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : (is_string($value) ? $value : null);
    }
}
