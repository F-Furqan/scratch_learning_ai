<?php

namespace App\Http\Controllers\Creator;

use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Content\BlogWorkflowService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreatorBlogController extends Controller
{
    public function __construct(private readonly ContentSanitizer $contentSanitizer) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->string('status')->toString();

        $posts = BlogPost::query()
            ->where('author_id', $user->id)
            ->with([
                'category',
                'approvalHistories.actor',
                'deletionRequests',
                'activeEditorialRevision.approvalHistories.actor',
                'latestEditorialRevision.approvalHistories.actor',
            ])
            ->withCount('comments')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('creator/ContentIndex', [
            'kind' => 'blog',
            'title' => 'Creator Blogs',
            'description' => 'Write, submit, and track review decisions for your blog articles.',
            'create_url' => route('creator.blogs.create', absolute: false),
            'filters' => ['status' => $status],
            'status_options' => $this->blogStatusOptions(),
            'summary' => $this->summary($user),
            'items' => $posts->through(fn (BlogPost $post): array => $this->postPayload($post, $user)),
            'can_create' => $user->can('create', BlogPost::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('create', BlogPost::class);

        return Inertia::render('creator/ContentForm', [
            'kind' => 'blog',
            'title' => 'New Blog Draft',
            'item' => null,
            'categories' => $this->blogCategories(),
            'store_url' => route('creator.blogs.store', absolute: false),
            'cancel_url' => route('creator.blogs.index', absolute: false),
        ]);
    }

    public function store(Request $request, ApprovalRecorder $approvals): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('create', BlogPost::class);

        $validated = $this->validatePost($request);

        $post = BlogPost::query()->create([
            ...$validated,
            'author_id' => $user->id,
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        $approvals->record($request, $post, 'draft_created', null, PublishStatus::Draft, 'Creator created blog draft.');

        return redirect()->route('creator.blogs.edit', $post);
    }

    public function edit(Request $request, BlogPost $post): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('update', $post) || $user->can('requestRevision', $post), 403);

        return Inertia::render('creator/ContentForm', [
            'kind' => 'blog',
            'title' => $user->can('requestRevision', $post) ? 'Submit Blog Revision' : 'Edit Blog Draft',
            'item' => $this->postPayload($post->load([
                'category',
                'approvalHistories.actor',
                'deletionRequests',
                'activeEditorialRevision.approvalHistories.actor',
                'latestEditorialRevision.approvalHistories.actor',
            ]), $user),
            'categories' => $this->blogCategories(),
            'store_url' => route('creator.blogs.store', absolute: false),
            'update_url' => route('creator.blogs.update', $post, absolute: false),
            'submit_url' => route('creator.blogs.submit', $post, absolute: false),
            'delete_request_url' => route('creator.blogs.delete-request', $post, absolute: false),
            'cancel_url' => route('creator.blogs.index', absolute: false),
        ]);
    }

    public function update(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $this->validatePost($request);

        if ($this->enumValue($post->getAttribute('status')) === PublishStatus::Published->value) {
            Gate::forUser($user)->authorize('requestRevision', $post);

            $this->recordCopyrightDeclaration($request, $post);
            $workflow->submitPublishedRevision($request, $post, $user, $validated);

            return redirect()
                ->route('creator.blogs.index', ['status' => PublishStatus::Published->value])
                ->with('status', 'creator-blog-revision-submitted');
        }

        Gate::forUser($user)->authorize('update', $post);

        $post->fill($validated);
        $post->save();

        return back()->with('status', 'creator-blog-updated');
    }

    public function submit(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('submit', $post);

        $this->recordCopyrightDeclaration($request, $post);
        $workflow->submitForReview($request, $post);

        return redirect()->route('creator.blogs.index', ['status' => PublishStatus::Submitted->value]);
    }

    public function requestDelete(Request $request, BlogPost $post, BlogWorkflowService $workflow): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('requestDelete', $post);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $workflow->requestDeletion($request, $post, $user, $validated['reason'] ?? null);

        return back()->with('status', 'creator-delete-requested');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $counts = BlogPost::query()
            ->where('author_id', $user->id)
            ->get(['status'])
            ->map(fn (BlogPost $post): string => $this->enumValue($post->getAttribute('status')) ?? 'unknown')
            ->countBy();

        return [
            'drafts' => (int) ($counts[PublishStatus::Draft->value] ?? 0),
            'pending_approval' => (int) (
                ($counts[PublishStatus::Pending->value] ?? 0)
                + ($counts[PublishStatus::Submitted->value] ?? 0)
                + ($counts[PublishStatus::Approved->value] ?? 0)
            ),
            'published' => (int) ($counts[PublishStatus::Published->value] ?? 0),
            'changes_requested' => (int) (
                ($counts[PublishStatus::ChangesRequested->value] ?? 0)
                + ($counts[PublishStatus::Rejected->value] ?? 0)
            ),
            'delete_requests' => CreatorContentDeletionRequest::query()
                ->where('requester_id', $user->id)
                ->where('status', CreatorContentDeletionStatus::Pending->value)
                ->where('content_type', (new BlogPost)->getMorphClass())
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blogCategories(): array
    {
        return BlogCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BlogCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(BlogPost $post, User $user): array
    {
        $latestHistory = $post->approvalHistories->first();
        $activeRevision = $post->activeEditorialRevision;
        $latestRevision = $activeRevision ?: $post->latestEditorialRevision;
        $pendingDeletion = $post->deletionRequests
            ->first(fn (CreatorContentDeletionRequest $request): bool => $this->enumValue($request->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'category_id' => $post->blog_category_id,
            'category' => $post->category?->name,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'seo_title' => $post->getAttribute('seo_title'),
            'seo_description' => $post->getAttribute('seo_description'),
            'status' => $this->enumValue($post->getAttribute('status')),
            'published_at' => $this->dateString($post->getAttribute('published_at')),
            'updated_at' => $this->dateString($post->getAttribute('updated_at')),
            'rejection_reason' => $post->rejection_reason,
            'admin_notes' => $post->admin_notes,
            'engagement_label' => $post->comments_count.' comments',
            'edit_url' => $user->can('update', $post) || $user->can('requestRevision', $post) ? route('creator.blogs.edit', $post, absolute: false) : null,
            'submit_url' => $user->can('submit', $post) ? route('creator.blogs.submit', $post, absolute: false) : null,
            'delete_request_url' => $user->can('requestDelete', $post) ? route('creator.blogs.delete-request', $post, absolute: false) : null,
            'public_url' => $post->isPublished() ? route('public.blog.show', $post->slug, absolute: false) : null,
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
    private function validatePost(Request $request): array
    {
        $data = $request->validate([
            'blog_category_id' => ['nullable', Rule::exists('blog_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['content'] = $this->contentSanitizer->richText($data['content']);

        return $data;
    }

    private function recordCopyrightDeclaration(Request $request, BlogPost $post): void
    {
        $request->validate([
            'copyright_declaration_accepted' => ['accepted'],
        ]);

        $post->forceFill([
            'copyright_declaration_accepted_at' => now(),
            'copyright_declaration_ip' => $request->ip(),
            'copyright_declaration_user_agent' => str($request->userAgent() ?? '')->limit(2000, '')->toString(),
        ])->save();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function blogStatusOptions(): array
    {
        return collect([
            PublishStatus::Draft,
            PublishStatus::Submitted,
            PublishStatus::Approved,
            PublishStatus::Published,
            PublishStatus::ChangesRequested,
            PublishStatus::Rejected,
            PublishStatus::DeleteRequested,
            PublishStatus::Trashed,
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

    private function revisionReviewNote(EditorialRevision $revision): ?string
    {
        $payload = $revision->getAttribute('payload');

        return is_array($payload) && is_string($payload['review_note'] ?? null)
            ? $payload['review_note']
            : null;
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : (is_string($value) ? $value : null);
    }
}
