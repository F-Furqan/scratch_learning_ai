<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPricingModel;
use App\Enums\AdvertiserRequestStatus;
use App\Enums\AdZoneStatus;
use App\Enums\AffiliateStatus;
use App\Enums\BloggerStatus;
use App\Enums\CheckoutRecoveryStatus;
use App\Enums\CommunityContentStatus;
use App\Enums\CommunityReportStatus;
use App\Enums\CommunityVisibility;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\DiscountType;
use App\Enums\EditorialRevisionStatus;
use App\Enums\GiftPurchaseStatus;
use App\Enums\GrowthStatus;
use App\Enums\HomeHeroMode;
use App\Enums\HomePageSectionType;
use App\Enums\InstructorProfileStatus;
use App\Enums\LeadSubmissionStatus;
use App\Enums\MediaVisibility;
use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentCheckoutStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Enums\PaymentSubscriptionStatus;
use App\Enums\PublishStatus;
use App\Enums\ReferralConversionStatus;
use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use App\Enums\RoleName;
use App\Enums\ScheduledPublicationStatus;
use App\Enums\TeamAccountStatus;
use App\Enums\TeamSeatStatus;
use App\Enums\UserStatus;
use App\Enums\VideoType;
use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckoutRecovery;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPricingSetting;
use App\Models\AdvertiserRequest;
use App\Models\AdZone;
use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use App\Models\AuthorBadge;
use App\Models\BlockedWord;
use App\Models\BlogCategory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\CommunityGroup;
use App\Models\ContentReport;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\CreatorContentDeletionRequest;
use App\Models\DiscussionForum;
use App\Models\DiscussionThread;
use App\Models\EditorialRevision;
use App\Models\GiftPurchase;
use App\Models\HomeHeroSlide;
use App\Models\HomePageSection;
use App\Models\InstructorProfile;
use App\Models\LeadMagnet;
use App\Models\LeadSubmission;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\Menu;
use App\Models\ModerationQueueItem;
use App\Models\NewsletterCampaign;
use App\Models\Page;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentOrder;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentSubscription;
use App\Models\ReferralConversion;
use App\Models\RevenueShareRule;
use App\Models\ReviewerComment;
use App\Models\ScheduledPublication;
use App\Models\SiteSetting;
use App\Models\SocialShareImage;
use App\Models\TeamAccount;
use App\Models\TeamSeat;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Admin\AuditLogger;
use App\Services\Community\ModerationService;
use App\Support\Security\ContentSanitizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Actions\BulkAdminResourceAction;
use Modules\Admin\Actions\CreateAdminResourceAction;
use Modules\Admin\Actions\DeleteAdminResourceAction;
use Modules\Admin\Actions\UpdateAdminResourceAction;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Http\Requests\BulkAdminResourceRequest;
use Modules\Admin\Http\Requests\DeleteAdminResourceRequest;
use Modules\Admin\Http\Requests\IndexAdminResourceRequest;
use Modules\Admin\Http\Requests\StoreAdminResourceRequest;
use Modules\Admin\Http\Requests\UpdateAdminResourceRequest;
use Modules\Admin\Http\Resources\AdminTableRecordResource;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Query\AdminResourceQueryFactory;
use Modules\Admin\Query\AdminResourceQueryFilter;
use Modules\Admin\Registry\AdminResourceRegistry;
use Modules\Admin\Services\CommerceOperationsAdminService;
use Modules\Admin\Services\CommunityOperationsAdminService;
use Modules\Admin\Services\CourseCatalogAdminService;
use Modules\Admin\Services\EditorialCmsAdminService;
use Modules\Admin\Services\LearningAdministrationService;
use Modules\Admin\Services\OperationsCenterActionService;
use Modules\Admin\Services\OperationsCenterAdminService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class AdminOperationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ApprovalRecorder $approvalRecorder,
        private readonly ModerationService $moderationService,
        private readonly AdminResourceRegistry $resourceRegistry,
        private readonly AdminResourceQueryFactory $queryFactory,
        private readonly AdminResourceQueryFilter $queryFilter,
        private readonly CreateAdminResourceAction $createAction,
        private readonly UpdateAdminResourceAction $updateAction,
        private readonly DeleteAdminResourceAction $deleteAction,
        private readonly BulkAdminResourceAction $bulkAction,
        private readonly CourseCatalogAdminService $courseCatalog,
        private readonly EditorialCmsAdminService $editorialCms,
        private readonly LearningAdministrationService $learningAdministration,
        private readonly CommerceOperationsAdminService $commerceOperations,
        private readonly CommunityOperationsAdminService $communityOperations,
        private readonly OperationsCenterAdminService $operationsCenter,
        private readonly OperationsCenterActionService $operationsCenterActions,
        private readonly AdminResourcePolicy $resourcePolicy,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    abstract protected function domain(): AdminDomain;

    public function index(IndexAdminResourceRequest $request): Response
    {
        $resource = $this->resource($request);
        $definition = $request->definition();
        $user = $request->user();
        $canManage = $user instanceof User && $this->resourcePolicy->manage($user, $definition);
        $canCreateCategory = $user instanceof User && $this->resourcePolicy->manage(
            $user,
            $this->resourceRegistry->get('course_categories'),
        );
        $perPage = min(max($request->integer('per_page', 15), 5), 100);

        $records = $resource === 'trash'
            ? $this->trashPaginator($request, $perPage)
            : $this->queryFilter->apply($this->queryFactory->make($resource), $resource, $request)
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Model $record): array => AdminTableRecordResource::make(
                    $this->rowFor($resource, $record),
                )->resolve($request));

        return Inertia::render('admin/Operations', [
            'resource' => $resource,
            'title' => $definition->title,
            'basePath' => '/admin/'.$definition->path,
            'columns' => $this->columnsFor($resource),
            'fields' => $this->fieldsFor($resource, $canCreateCategory),
            'filters' => $this->filtersFor($resource),
            'filterValues' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', ''),
                'category' => $request->query('category', ''),
                'role' => $request->query('role', ''),
                'group' => $request->query('group', ''),
            ],
            'rows' => $records,
            'bulkActions' => $this->bulkActionsFor($resource),
            'mediaAssets' => $this->mediaPickerOptions(),
            'metrics' => $this->metricsFor($resource),
            'canCreate' => $resource !== 'trash'
                && ! $this->editorialCms->readOnly($resource)
                && ! $this->learningAdministration->readOnly($resource)
                && ! $this->commerceOperations->readOnly($resource)
                && ! $this->communityOperations->readOnly($resource)
                && ! $this->operationsCenter->readOnly($resource)
                && $canManage,
            'canEdit' => $resource !== 'trash'
                && ! $this->editorialCms->readOnly($resource)
                && ! $this->learningAdministration->readOnly($resource)
                && ! $this->commerceOperations->readOnly($resource)
                && ! $this->communityOperations->readOnly($resource)
                && ! $this->operationsCenter->readOnly($resource)
                && $canManage,
            'canDelete' => $resource !== 'trash'
                && ! $this->editorialCms->readOnly($resource)
                && ! $this->learningAdministration->readOnly($resource)
                && ! $this->commerceOperations->readOnly($resource)
                && ! $this->communityOperations->readOnly($resource)
                && ! $this->operationsCenter->readOnly($resource)
                && (! $this->learningAdministration->supports($resource) || $this->learningAdministration->deletable($resource))
                && $canManage,
            'exportUrl' => $user instanceof User
                && $user->can('admin.records.export')
                && ($this->commerceOperations->exportable($resource)
                    || $this->communityOperations->exportable($resource)
                    || $this->operationsCenter->exportable($resource))
                    ? route('admin.operational-records.export', ['resource' => $resource, ...$request->only(['search', 'status', 'category'])], false)
                    : null,
            'ordering' => [
                'enabled' => ($this->courseCatalog->reorderable($resource) || $this->editorialCms->reorderable($resource))
                    && $canManage
                    && ($this->editorialCms->reorderable($resource) || $user->can('admin.catalog.reorder')),
                'url' => ($this->courseCatalog->reorderable($resource) || $this->editorialCms->reorderable($resource))
                    && $canManage
                    && ($this->editorialCms->reorderable($resource) || $user->can('admin.catalog.reorder'))
                    ? '/admin/'.$definition->path.'/reorder'
                    : null,
            ],
        ]);
    }

    public function store(StoreAdminResourceRequest $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $this->guardMutableResource($resource);

        $this->createAction->execute(
            $request,
            $resource,
            fn (): Model => $this->persist($request, $resource),
        );

        return back()->with('success', $request->definition()->singular.' created.');
    }

    public function update(UpdateAdminResourceRequest $request, int $id): RedirectResponse
    {
        $resource = $this->resource($request);
        $this->guardMutableResource($resource);
        $record = $this->findRecord($resource, $id);

        $this->updateAction->execute($request, $resource, $record, function () use ($request, $resource, $record): Model {
            $fromStatus = $record->getAttribute('status') ?? $record->getAttribute('visibility');
            $updated = $this->persist($request, $resource, $record);
            $toStatus = $updated->getAttribute('status') ?? $updated->getAttribute('visibility');

            $this->recordApprovalIfNeeded($request, $resource, $updated, $fromStatus, $toStatus, $this->decisionNote($request));

            return $updated;
        });

        return back()->with('success', $request->definition()->singular.' updated.');
    }

    public function destroy(DeleteAdminResourceRequest $request, int $id): RedirectResponse
    {
        $resource = $this->resource($request);
        $this->guardMutableResource($resource);
        $record = $this->findRecord($resource, $id);

        $this->guardDeletion($request, $resource, $record);

        $this->deleteAction->execute($request, $resource, $record, function (Model $record): void {
            $this->markContentAsTrashed($record);
            $record->delete();
        });

        return back()->with('success', $request->definition()->singular.' deleted.');
    }

    public function bulk(BulkAdminResourceRequest $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $data = $request->validated();
        $this->validateBulkAction($resource, $data);

        if ($this->operationsCenter->supports($resource)) {
            $this->operationsCenterActions->bulk($request, $resource, $data);

            return back()->with('success', 'Operational action queued or completed.');
        }

        $this->guardMutableResource($resource);

        if ($this->courseCatalog->supports($resource)) {
            $this->courseCatalog->validateBulk($request, $resource, $data);
        }

        if ($this->editorialCms->supports($resource)) {
            $this->editorialCms->validateBulk($resource, $data);
        }

        $records = $this->queryFactory->make($resource)
            ->whereKey($data['ids'])
            ->get();

        if ($records->count() !== count(array_unique($data['ids']))) {
            throw ValidationException::withMessages([
                'ids' => 'One or more selected records no longer exist. Refresh the page and try again.',
            ]);
        }

        $this->bulkAction->execute(function () use ($request, $resource, $records, $data): void {
            foreach ($records as $record) {
                $before = $record->toArray();

                if ($data['action'] === 'delete') {
                    $this->guardDeletion($request, $resource, $record);
                    $this->markContentAsTrashed($record);
                    $this->auditLogger->log($request, "admin.{$resource}.bulk_deleted", $record, $before, $record->fresh()?->toArray(), [
                        'note' => $data['note'] ?? null,
                    ]);
                    $record->delete();

                    continue;
                }

                $fromStatus = $record->getAttribute('status') ?? $record->getAttribute('visibility');
                $this->applyBulkMutation($resource, $record, (string) $data['action'], $data['value'] ?? null, $data['note'] ?? null, $request);
                $toStatus = $record->getAttribute('status') ?? $record->getAttribute('visibility');

                $this->recordApprovalIfNeeded($request, $resource, $record, $fromStatus, $toStatus, $data['note'] ?? null);
                $this->auditLogger->log($request, "admin.{$resource}.bulk_{$data['action']}", $record, $before, $record->fresh()?->toArray(), [
                    'value' => $data['value'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Bulk action completed.');
    }

    /** @param array<string, mixed> $data */
    private function validateBulkAction(string $resource, array $data): void
    {
        $action = collect($this->bulkActionsFor($resource))
            ->firstWhere('value', $data['action']);

        if (! is_array($action)) {
            throw ValidationException::withMessages(['action' => 'The selected bulk action is not available.']);
        }

        $options = collect($action['options'] ?? [])
            ->pluck('value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        if ($options !== [] && ! in_array((string) ($data['value'] ?? ''), $options, true)) {
            throw ValidationException::withMessages(['value' => 'Select a valid value for this action.']);
        }

        if (($action['needsNote'] ?? false) && blank($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => 'A review note is required for this action.']);
        }
    }

    private function resource(Request $request): string
    {
        $resource = (string) $request->route('resource');
        $definition = $this->resourceRegistry->get($resource);

        abort_unless($definition->domain === $this->domain(), 404);

        return $resource;
    }

    private function guardMutableResource(string $resource): void
    {
        abort_if($resource === 'trash', 405, 'Use the trash restore or permanent delete actions.');
        abort_if($this->editorialCms->readOnly($resource), 405, 'This history resource is read-only.');
        abort_if($this->learningAdministration->readOnly($resource), 405, 'This learning activity record is read-only. Use its controlled review action.');
        abort_if($this->commerceOperations->readOnly($resource), 405, 'This financial record is read-only. Use an authorized operational action.');
        abort_if($this->communityOperations->readOnly($resource), 405, 'This community activity record is read-only. Use an authorized moderation action.');
        abort_if($this->operationsCenter->readOnly($resource), 405, 'This operational evidence is read-only. Use an authorized operational action.');
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function trashPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        $search = $request->query('search');
        $type = $request->query('category');

        $rows = collect()
            ->merge(Course::onlyTrashed()
                ->with('creator')
                ->latest('deleted_at')
                ->get()
                ->map(fn (Course $course): array => $this->trashRow($course)))
            ->merge(BlogPost::onlyTrashed()
                ->with('author')
                ->latest('deleted_at')
                ->get()
                ->map(fn (BlogPost $post): array => $this->trashRow($post)));

        if (is_string($type) && filled($type)) {
            $rows = $rows->where('type_key', $type);
        }

        if (is_string($search) && filled($search)) {
            $needle = str($search)->lower()->toString();
            $rows = $rows->filter(fn (array $row): bool => str((string) ($row['title'] ?? ''))->lower()->contains($needle)
                || str((string) ($row['creator'] ?? ''))->lower()->contains($needle)
                || str((string) ($row['type'] ?? ''))->lower()->contains($needle));
        }

        $rows = $rows->sortByDesc('deleted_timestamp')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function trashRow(Course|BlogPost $record): array
    {
        $type = $record instanceof Course ? 'course' : 'blog';
        $deletedAt = $record->getAttribute('deleted_at');

        return [
            'id' => $type.':'.$record->getKey(),
            'content_id' => $record->getKey(),
            'type_key' => $type,
            'title' => $record->title,
            'type' => $record instanceof Course ? 'Course' : 'Blog',
            'status' => $this->statusValue($record->getAttribute('status')),
            'creator' => $record instanceof Course ? $record->creator?->name : $record->author?->name,
            'deleted_at' => $this->dateTimeString($deletedAt),
            'deleted_timestamp' => $deletedAt instanceof CarbonInterface ? $deletedAt->getTimestamp() : 0,
            'form' => [],
            'workflow_actions' => [
                $this->workflowAction('Restore', route('admin.trash.restore', ['type' => $type, 'id' => $record->getKey()], false), 'success'),
                $this->workflowAction('Delete Forever', route('admin.trash.force-delete', ['type' => $type, 'id' => $record->getKey()], false), 'danger', 'delete'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columnsFor(string $resource): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->columns($resource);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->columns($resource);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->columns($resource);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->columns($resource);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->columns($resource);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->columns($resource);
        }

        return match ($resource) {
            'users' => $this->columns(['name', 'email', 'status', 'roles', 'created_at']),
            'roles' => $this->columns(['name', 'permissions_count', 'permissions']),
            'permissions' => $this->columns(['name', 'guard_name']),
            'bloggers' => $this->columns(['user', 'email', 'status', 'expertise', 'reviewed_by', 'history_count']),
            'courses' => $this->columns(['title', 'status', 'creator', 'category', 'ownership', 'curriculum', 'price', 'published_at']),
            'lessons' => $this->columns(['title', 'course', 'status', 'order_number', 'is_free', 'published_at']),
            'blogs' => $this->columns(['title', 'status', 'category', 'author', 'is_featured', 'published_at']),
            'cms' => $this->columns(['title', 'status', 'template', 'author', 'published_at']),
            'home_hero' => $this->columns(['mode', 'eyebrow', 'heading', 'search_enabled', 'featured_course_enabled']),
            'home_hero_slides' => $this->columns(['title', 'is_active', 'target_url', 'button_label', 'sort_order', 'image_url']),
            'home_page_sections' => $this->columns(['key', 'type', 'title', 'is_active', 'sort_order', 'cta_url']),
            'menus' => $this->columns(['name', 'location', 'is_active', 'items_count']),
            'media' => $this->columns(['title', 'visibility', 'mime_type', 'dimensions', 'path']),
            'ad_zones' => $this->columns(['name', 'location', 'status', 'dimensions', 'creatives_count', 'impressions_count', 'clicks_count']),
            'ad_campaigns' => $this->columns(['name', 'advertiser_name', 'status', 'pricing_model', 'budget_total', 'starts_at', 'ends_at']),
            'ad_creatives' => $this->columns(['name', 'campaign', 'zone', 'type', 'status', 'impressions_count', 'clicks_count']),
            'advertiser_requests' => $this->columns(['company_name', 'contact_name', 'email', 'status', 'requested_zone', 'budget_range']),
            'ad_pricing' => $this->columns(['name', 'zone', 'pricing_model', 'currency', 'cpm_rate', 'cpc_rate', 'flat_rate', 'is_active']),
            'instructors' => $this->columns(['display_name', 'user', 'status', 'expertise', 'is_verified_expert', 'accepts_revenue_share']),
            'author_badges' => $this->columns(['name', 'slug', 'marks_verified_expert', 'is_active', 'users_count']),
            'editorial_revisions' => $this->columns(['title', 'status', 'author', 'reviewer', 'comments_count', 'scheduled_at']),
            'reviewer_comments' => $this->columns(['revision', 'reviewer', 'field_path', 'is_resolved', 'created_at']),
            'scheduled_publications' => $this->columns(['publishable', 'status', 'publish_at', 'creator', 'approved_by', 'published_at']),
            'revenue_share_rules' => $this->columns(['user', 'course', 'type', 'status', 'share_percent', 'currency']),
            'payment_products' => $this->columns(['name', 'type', 'status', 'course', 'bundle', 'paddle_product_id', 'prices_count']),
            'payment_prices' => $this->columns(['name', 'product', 'billing_interval', 'amount', 'is_active', 'paddle_price_id']),
            'payment_discounts' => $this->columns(['name', 'code', 'type', 'value', 'status', 'redemptions_count']),
            'payment_checkouts' => $this->columns(['user', 'product', 'status', 'quantity', 'discount', 'paddle_transaction_id', 'expires_at']),
            'payment_orders' => $this->columns(['user', 'status', 'total', 'paddle_transaction_id', 'paddle_subscription_id', 'purchased_at']),
            'payment_subscriptions' => $this->columns(['user', 'product', 'status', 'quantity', 'paddle_subscription_id', 'next_billed_at']),
            'team_accounts' => $this->columns(['name', 'owner', 'status', 'seat_limit', 'seats_count', 'paddle_subscription_id']),
            'team_seats' => $this->columns(['email', 'team', 'user', 'role', 'status', 'accepted_at']),
            'payment_reconciliation' => $this->columns(['record_type', 'status', 'event_id', 'paddle_transaction_id', 'paddle_subscription_id', 'reconciled_at']),
            'gift_purchases' => $this->columns(['recipient_email', 'status', 'product', 'course', 'code', 'purchased_at']),
            'affiliate_partners' => $this->columns(['name', 'code', 'status', 'commission_rate', 'visits_count', 'conversions_count']),
            'affiliate_visits' => $this->columns(['partner', 'visitor_id', 'landing_url', 'clicked_at', 'expires_at']),
            'referral_conversions' => $this->columns(['partner', 'user', 'status', 'amount', 'commission_amount', 'converted_at']),
            'checkout_recoveries' => $this->columns(['email', 'product', 'status', 'reminder_count', 'recovered_at', 'expires_at']),
            'lead_magnets' => $this->columns(['title', 'slug', 'status', 'submissions_count', 'delivery_url']),
            'newsletter_campaigns' => $this->columns(['name', 'subject', 'status', 'lead_magnet', 'scheduled_at', 'sent_at']),
            'lead_submissions' => $this->columns(['email', 'name', 'status', 'lead_magnet', 'created_at']),
            'ab_experiments' => $this->columns(['key', 'name', 'surface', 'status', 'variants_count', 'winning_variant_key']),
            'ab_variants' => $this->columns(['experiment', 'key', 'name', 'weight', 'views_count', 'conversions_count']),
            'social_share_images' => $this->columns(['title', 'shareable', 'template', 'status', 'image_url']),
            'moderation_queue' => $this->columns(['subject', 'status', 'reason', 'spam_score', 'reporter', 'reviewed_at']),
            'content_reports' => $this->columns(['reportable', 'status', 'reason', 'reporter', 'reviewed_by', 'reviewed_at']),
            'blocked_words' => $this->columns(['word', 'match_type', 'severity', 'is_active']),
            'discussion_forums' => $this->columns(['title', 'course', 'visibility', 'status', 'threads_count', 'posts_count']),
            'discussion_threads' => $this->columns(['title', 'forum', 'author', 'status', 'replies_count', 'upvotes_count']),
            'community_groups' => $this->columns(['name', 'course', 'visibility', 'status', 'requires_paid_access', 'members_count']),
            'trash' => $this->columns(['title', 'type', 'status', 'creator', 'deleted_at']),
            'settings' => $this->columns(['group', 'key', 'value', 'is_encrypted']),
            default => [],
        };
    }

    /**
     * @param  list<string>  $keys
     * @return array<int, array{key: string, label: string}>
     */
    private function columns(array $keys): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $keys);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(string $resource, bool $canCreateCategory = false): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->fields($resource);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->fields($resource);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->fields($resource);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->fields($resource);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->fields($resource);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->fields($resource);
        }

        return match ($resource) {
            'users' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('email', 'Email', 'email', required: true),
                $this->field('password', 'Password', 'password'),
                $this->field('status', 'Status', 'select', $this->enumOptions(UserStatus::cases()), true),
                $this->field('roles', 'Roles', 'multiselect', $this->roleOptions()),
            ],
            'roles' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('permissions', 'Permissions', 'multiselect', $this->permissionOptions()),
            ],
            'permissions' => [
                $this->field('name', 'Name', 'text', required: true),
            ],
            'bloggers' => [
                $this->field('user_id', 'User', 'select', $this->userOptions(), true),
                $this->field('phone', 'Phone', 'text'),
                $this->field('expertise', 'Expertise', 'text'),
                $this->field('bio', 'Bio', 'textarea'),
                $this->field('application_reason', 'Application Reason', 'textarea'),
                $this->field('status', 'Status', 'select', $this->enumOptions(BloggerStatus::cases()), true),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
            ],
            'courses' => [
                $this->field('title', 'Title', 'text', required: true),
                $this->field('course_category_id', 'Category', 'select', $this->courseCategoryOptions()) + ($canCreateCategory ? [
                    'inlineCreate' => [
                        'label' => 'Add Category',
                        'url' => '/admin/course-categories/quick',
                    ],
                ] : []),
                $this->field('course_subcategory_id', 'Subcategory', 'select', $this->courseSubcategoryOptions()) + [
                    'dependsOn' => 'course_category_id',
                    'optionsUrl' => '/admin/catalog/course-categories/{value}/subcategories',
                ],
                $this->field('thumbnail_media_id', 'Thumbnail', 'media'),
                $this->field('ownership_video_media_id', 'Ownership Video', 'media'),
                $this->field('ownership_video_url', 'Ownership Video URL', 'text'),
                $this->field('ownership_statement', 'Ownership Statement', 'textarea'),
                $this->field('short_description', 'Short Description', 'textarea'),
                $this->field('description', 'Description', 'textarea'),
                $this->field('level', 'Level', 'text'),
                $this->field('language', 'Language', 'text'),
                $this->field('price', 'Price', 'number'),
                $this->field('is_free', 'Free', 'checkbox'),
                $this->field('status', 'Status', 'select', $this->standardPublishStatusOptions(), true),
                $this->field('seo_title', 'SEO Title', 'text'),
                $this->field('seo_description', 'SEO Description', 'textarea'),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
                $this->field('rejection_reason', 'Rejection Reason', 'textarea'),
            ],
            'lessons' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('course_section_id', 'Section', 'select', $this->courseSectionOptions()),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('order_number', 'Order', 'number'),
                $this->field('content', 'Content', 'richtext'),
                $this->field('video_type', 'Video Type', 'select', $this->enumOptions(VideoType::cases()), true),
                $this->field('video_url', 'Video URL', 'text'),
                $this->field('video_file_id', 'Video File', 'media'),
                $this->field('is_free', 'Free', 'checkbox'),
                $this->field('is_paid', 'Paid', 'checkbox'),
                $this->field('preview_word_limit', 'Preview Words', 'number'),
                $this->field('status', 'Status', 'select', $this->standardPublishStatusOptions(), true),
                $this->field('seo_title', 'SEO Title', 'text'),
                $this->field('seo_description', 'SEO Description', 'textarea'),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
                $this->field('rejection_reason', 'Rejection Reason', 'textarea'),
            ],
            'blogs' => [
                $this->field('title', 'Title', 'text', required: true),
                $this->field('blog_category_id', 'Category', 'select', $this->blogCategoryOptions()),
                $this->field('author_id', 'Author', 'select', $this->userOptions()),
                $this->field('featured_image_media_id', 'Featured Image', 'media'),
                $this->field('blog_tag_ids', 'Tags', 'multiselect', $this->blogTagOptions()),
                $this->field('excerpt', 'Excerpt (Short Summary)', 'textarea'),
                $this->field('content', 'Content', 'richtext'),
                $this->field('status', 'Status', 'select', $this->blogWorkflowStatusOptions(), true),
                $this->field('is_featured', 'Featured', 'checkbox'),
                $this->field('seo_title', 'SEO Title', 'text'),
                $this->field('seo_description', 'SEO Description', 'textarea'),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
                $this->field('rejection_reason', 'Rejection Reason', 'textarea'),
            ],
            'cms' => [
                $this->field('title', 'Title', 'text', required: true),
                $this->field('author_id', 'Author', 'select', $this->userOptions()),
                $this->field('excerpt', 'Excerpt', 'textarea'),
                $this->field('content', 'Content', 'richtext'),
                $this->field('template', 'Template', 'text'),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('status', 'Status', 'select', $this->standardPublishStatusOptions(), true),
                $this->field('seo_title', 'SEO Title', 'text'),
                $this->field('seo_description', 'SEO Description', 'textarea'),
                $this->field('seo_image', 'SEO Image', 'media'),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
                $this->field('rejection_reason', 'Rejection Reason', 'textarea'),
            ],
            'home_hero' => [
                $this->field('mode', 'Hero Mode', 'select', $this->enumOptions(HomeHeroMode::cases()), true),
                $this->field('eyebrow', 'Design Eyebrow', 'text'),
                $this->field('heading', 'Design Heading', 'text'),
                $this->field('highlight_terms', 'Highlighted Words', 'text'),
                $this->field('description', 'Design Description', 'textarea'),
                $this->field('search_enabled', 'Show Search In Design Mode', 'checkbox'),
                $this->field('stats_enabled', 'Show Stats In Design Mode', 'checkbox'),
                $this->field('featured_course_enabled', 'Show Featured Course In Design Mode', 'checkbox'),
            ],
            'home_hero_slides' => [
                $this->field('media_asset_id', 'Slide Image', 'media'),
                $this->field('image_url', 'Fallback Image URL', 'text'),
                $this->field('image_alt', 'Image Alt Text', 'text'),
                $this->field('eyebrow', 'Eyebrow', 'text'),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('subtitle', 'Subtitle', 'textarea'),
                $this->field('button_label', 'Button Label', 'text'),
                $this->field('target_url', 'Click Target URL', 'text', required: true),
                $this->field('text_position', 'Text Position', 'select', [
                    ['label' => 'Left', 'value' => 'left'],
                    ['label' => 'Center', 'value' => 'center'],
                    ['label' => 'Right', 'value' => 'right'],
                ], true),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('opens_in_new_tab', 'Open In New Tab', 'checkbox'),
            ],
            'home_page_sections' => [
                $this->field('key', 'Unique Key', 'text', required: true),
                $this->field('type', 'Section Type', 'select', $this->enumOptions(HomePageSectionType::cases()), true),
                $this->field('eyebrow', 'Eyebrow', 'text'),
                $this->field('title', 'Title', 'text'),
                $this->field('subtitle', 'Subtitle', 'textarea'),
                $this->field('body', 'Body', 'textarea'),
                $this->field('cta_label', 'CTA Label', 'text'),
                $this->field('cta_url', 'CTA URL', 'text'),
                $this->field('background', 'Background', 'select', [
                    ['label' => 'White', 'value' => 'white'],
                    ['label' => 'Soft', 'value' => 'soft'],
                    ['label' => 'Dark', 'value' => 'dark'],
                    ['label' => 'Accent', 'value' => 'accent'],
                ], true),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('payload_content', 'Section Payload JSON', 'textarea'),
            ],
            'menus' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('location', 'Location', 'text'),
                $this->field('is_active', 'Active', 'checkbox'),
            ],
            'media' => [
                $this->field('folder_id', 'Folder', 'select', $this->mediaFolderOptions()),
                $this->field('uploaded_by', 'Uploader', 'select', $this->userOptions()),
                $this->field('title', 'Title', 'text'),
                $this->field('path', 'Path', 'text', required: true),
                $this->field('url', 'URL', 'text'),
                $this->field('alt_text', 'Alt Text', 'text'),
                $this->field('caption', 'Caption', 'textarea'),
                $this->field('mime_type', 'MIME Type', 'text'),
                $this->field('size', 'Size', 'number'),
                $this->field('width', 'Width', 'number'),
                $this->field('height', 'Height', 'number'),
                $this->field('visibility', 'Visibility', 'select', $this->enumOptions(MediaVisibility::cases()), true),
            ],
            'ad_zones' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('location', 'Location', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('width', 'Width', 'number'),
                $this->field('height', 'Height', 'number'),
                $this->field('max_creatives', 'Max Creatives', 'number'),
                $this->field('status', 'Status', 'select', $this->enumOptions(AdZoneStatus::cases()), true),
            ],
            'ad_campaigns' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('advertiser_name', 'Advertiser', 'text', required: true),
                $this->field('advertiser_email', 'Advertiser Email', 'email'),
                $this->field('status', 'Status', 'select', $this->enumOptions(AdCampaignStatus::cases()), true),
                $this->field('pricing_model', 'Pricing Model', 'select', $this->enumOptions(AdPricingModel::cases()), true),
                $this->field('currency', 'Currency', 'text'),
                $this->field('budget_total', 'Total Budget', 'number'),
                $this->field('daily_budget', 'Daily Budget', 'number'),
                $this->field('cpm_rate', 'CPM Rate', 'number'),
                $this->field('cpc_rate', 'CPC Rate', 'number'),
                $this->field('flat_rate', 'Flat Rate', 'number'),
                $this->field('target_url', 'Target URL', 'text'),
                $this->field('starts_at', 'Starts At', 'text'),
                $this->field('ends_at', 'Ends At', 'text'),
                $this->field('notes', 'Notes', 'textarea'),
            ],
            'ad_creatives' => [
                $this->field('ad_campaign_id', 'Campaign', 'select', $this->adCampaignOptions(), true),
                $this->field('ad_zone_id', 'Zone', 'select', $this->adZoneOptions()),
                $this->field('media_asset_id', 'Media Asset', 'media'),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('type', 'Type', 'select', $this->enumOptions(AdCreativeType::cases()), true),
                $this->field('status', 'Status', 'select', $this->enumOptions(AdCreativeStatus::cases()), true),
                $this->field('headline', 'Headline', 'text'),
                $this->field('body', 'Body', 'textarea'),
                $this->field('cta_text', 'CTA Text', 'text'),
                $this->field('target_url', 'Target URL', 'text'),
                $this->field('html_snippet', 'HTML Snippet', 'textarea'),
                $this->field('weight', 'Weight', 'number'),
                $this->field('starts_at', 'Starts At', 'text'),
                $this->field('ends_at', 'Ends At', 'text'),
            ],
            'advertiser_requests' => [
                $this->field('requested_ad_zone_id', 'Requested Zone', 'select', $this->adZoneOptions()),
                $this->field('company_name', 'Company', 'text', required: true),
                $this->field('contact_name', 'Contact', 'text', required: true),
                $this->field('email', 'Email', 'email', required: true),
                $this->field('phone', 'Phone', 'text'),
                $this->field('website_url', 'Website', 'text'),
                $this->field('budget_min', 'Budget Min', 'number'),
                $this->field('budget_max', 'Budget Max', 'number'),
                $this->field('message', 'Message', 'textarea'),
                $this->field('status', 'Status', 'select', $this->enumOptions(AdvertiserRequestStatus::cases()), true),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
            ],
            'ad_pricing' => [
                $this->field('ad_zone_id', 'Zone', 'select', $this->adZoneOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('pricing_model', 'Pricing Model', 'select', $this->enumOptions(AdPricingModel::cases()), true),
                $this->field('currency', 'Currency', 'text'),
                $this->field('cpm_rate', 'CPM Rate', 'number'),
                $this->field('cpc_rate', 'CPC Rate', 'number'),
                $this->field('flat_rate', 'Flat Rate', 'number'),
                $this->field('min_spend', 'Min Spend', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('effective_from', 'Effective From', 'text'),
                $this->field('effective_until', 'Effective Until', 'text'),
                $this->field('notes', 'Notes', 'textarea'),
            ],
            'instructors' => [
                $this->field('user_id', 'User', 'select', $this->userOptions(), true),
                $this->field('avatar_media_id', 'Avatar', 'media'),
                $this->field('display_name', 'Display Name', 'text', required: true),
                $this->field('headline', 'Headline', 'text'),
                $this->field('bio', 'Bio', 'textarea'),
                $this->field('credentials', 'Credentials', 'textarea'),
                $this->field('expertise', 'Expertise', 'text'),
                $this->field('website_url', 'Website', 'text'),
                $this->field('linkedin_url', 'LinkedIn', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(InstructorProfileStatus::cases()), true),
                $this->field('is_verified_expert', 'Verified Expert', 'checkbox'),
                $this->field('accepts_revenue_share', 'Accepts Revenue Share', 'checkbox'),
                $this->field('payout_currency', 'Payout Currency', 'text'),
                $this->field('payout_account_reference', 'Payout Reference', 'text'),
                $this->field('admin_notes', 'Admin Notes', 'textarea'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'author_badges' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('icon', 'Icon', 'text'),
                $this->field('color', 'Color', 'text'),
                $this->field('marks_verified_expert', 'Marks Verified Expert', 'checkbox'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('user_ids', 'Awarded Authors', 'multiselect', $this->userOptions()),
            ],
            'editorial_revisions' => [
                $this->field('author_id', 'Author', 'select', $this->userOptions()),
                $this->field('reviewer_id', 'Reviewer', 'select', $this->userOptions()),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('summary', 'Summary', 'textarea'),
                $this->field('payload_content', 'Payload', 'textarea'),
                $this->field('status', 'Status', 'select', $this->enumOptions(EditorialRevisionStatus::cases()), true),
                $this->field('submitted_at', 'Submitted At', 'text'),
                $this->field('reviewed_at', 'Reviewed At', 'text'),
                $this->field('scheduled_at', 'Scheduled At', 'text'),
            ],
            'reviewer_comments' => [
                $this->field('editorial_revision_id', 'Revision', 'select', $this->editorialRevisionOptions(), true),
                $this->field('reviewer_id', 'Reviewer', 'select', $this->userOptions()),
                $this->field('field_path', 'Field Path', 'text'),
                $this->field('body', 'Comment', 'textarea', required: true),
                $this->field('is_resolved', 'Resolved', 'checkbox'),
            ],
            'scheduled_publications' => [
                $this->field('editorial_revision_id', 'Revision', 'select', $this->editorialRevisionOptions()),
                $this->field('created_by', 'Creator', 'select', $this->userOptions()),
                $this->field('approved_by', 'Approver', 'select', $this->userOptions()),
                $this->field('publish_at', 'Publish At', 'text', required: true),
                $this->field('timezone', 'Timezone', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(ScheduledPublicationStatus::cases()), true),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'revenue_share_rules' => [
                $this->field('user_id', 'Instructor User', 'select', $this->userOptions()),
                $this->field('instructor_profile_id', 'Instructor Profile', 'select', $this->instructorProfileOptions()),
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('payment_product_id', 'Product', 'select', $this->paymentProductOptions()),
                $this->field('type', 'Type', 'select', $this->enumOptions(RevenueShareRuleType::cases()), true),
                $this->field('status', 'Status', 'select', $this->enumOptions(RevenueShareRuleStatus::cases()), true),
                $this->field('share_percent', 'Share Percent', 'number'),
                $this->field('fixed_amount_cents', 'Fixed Amount Cents', 'number'),
                $this->field('currency', 'Currency', 'text'),
                $this->field('starts_at', 'Starts At', 'text'),
                $this->field('ends_at', 'Ends At', 'text'),
                $this->field('notes', 'Notes', 'textarea'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'payment_products' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('course_bundle_id', 'Bundle', 'select', $this->courseBundleOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('type', 'Type', 'select', $this->enumOptions(PaymentProductType::cases()), true),
                $this->field('status', 'Status', 'select', $this->enumOptions(PaymentProductStatus::cases()), true),
                $this->field('paddle_product_id', 'Paddle Product ID', 'text'),
                $this->field('tax_category', 'Tax Category', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'payment_prices' => [
                $this->field('payment_product_id', 'Product', 'select', $this->paymentProductOptions(), true),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('paddle_price_id', 'Paddle Price ID', 'text'),
                $this->field('billing_interval', 'Billing Interval', 'select', $this->enumOptions(PaymentBillingInterval::cases()), true),
                $this->field('is_recurring', 'Recurring', 'checkbox'),
                $this->field('currency', 'Currency', 'text'),
                $this->field('amount', 'Amount In Cents', 'number', required: true),
                $this->field('trial_days', 'Trial Days', 'number'),
                $this->field('seat_min', 'Seat Min', 'number'),
                $this->field('seat_max', 'Seat Max', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'payment_discounts' => [
                $this->field('payment_product_id', 'Product', 'select', $this->paymentProductOptions()),
                $this->field('payment_price_id', 'Plan', 'select', $this->paymentPriceOptions()),
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('code', 'Code', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('type', 'Type', 'select', $this->enumOptions(DiscountType::cases()), true),
                $this->field('value', 'Value', 'number', required: true),
                $this->field('currency', 'Currency', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(GrowthStatus::cases()), true),
                $this->field('is_launch_offer', 'Launch Offer', 'checkbox'),
                $this->field('paddle_discount_id', 'Paddle Discount ID', 'text'),
                $this->field('max_redemptions', 'Max Redemptions', 'number'),
                $this->field('per_user_limit', 'Per User Limit', 'number'),
                $this->field('starts_at', 'Starts At', 'text'),
                $this->field('ends_at', 'Ends At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'payment_checkouts' => [
                $this->field('user_id', 'User', 'select', $this->userOptions(), true),
                $this->field('payment_price_id', 'Plan', 'select', $this->paymentPriceOptions(), true),
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('team_account_id', 'Team', 'select', $this->teamAccountOptions()),
                $this->field('payment_discount_id', 'Discount', 'select', $this->paymentDiscountOptions()),
                $this->field('quantity', 'Quantity', 'number'),
                $this->field('discount_amount', 'Discount Amount', 'number'),
                $this->field('status', 'Status', 'select', $this->enumOptions(PaymentCheckoutStatus::cases()), true),
                $this->field('paddle_transaction_id', 'Paddle Transaction ID', 'text'),
                $this->field('checkout_url', 'Checkout URL', 'text'),
                $this->field('recovery_email', 'Recovery Email', 'email'),
                $this->field('custom_data_content', 'Custom Data', 'textarea'),
                $this->field('expires_at', 'Expires At', 'text'),
            ],
            'payment_orders' => [
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('team_account_id', 'Team', 'select', $this->teamAccountOptions()),
                $this->field('payment_checkout_id', 'Checkout', 'select', $this->paymentCheckoutOptions()),
                $this->field('provider', 'Provider', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(PaymentOrderStatus::cases()), true),
                $this->field('paddle_transaction_id', 'Paddle Transaction ID', 'text'),
                $this->field('paddle_customer_id', 'Paddle Customer ID', 'text'),
                $this->field('paddle_subscription_id', 'Paddle Subscription ID', 'text'),
                $this->field('currency', 'Currency', 'text'),
                $this->field('subtotal', 'Subtotal', 'number'),
                $this->field('tax', 'Tax', 'number'),
                $this->field('discount', 'Discount', 'number'),
                $this->field('total', 'Total', 'number'),
                $this->field('purchased_at', 'Purchased At', 'text'),
                $this->field('payload_content', 'Payload', 'textarea'),
            ],
            'payment_subscriptions' => [
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('team_account_id', 'Team', 'select', $this->teamAccountOptions()),
                $this->field('payment_product_id', 'Product', 'select', $this->paymentProductOptions()),
                $this->field('payment_price_id', 'Plan', 'select', $this->paymentPriceOptions()),
                $this->field('provider', 'Provider', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(PaymentSubscriptionStatus::cases()), true),
                $this->field('paddle_subscription_id', 'Paddle Subscription ID', 'text', required: true),
                $this->field('paddle_customer_id', 'Paddle Customer ID', 'text'),
                $this->field('currency', 'Currency', 'text'),
                $this->field('quantity', 'Quantity', 'number'),
                $this->field('current_period_starts_at', 'Period Starts At', 'text'),
                $this->field('current_period_ends_at', 'Period Ends At', 'text'),
                $this->field('trial_ends_at', 'Trial Ends At', 'text'),
                $this->field('canceled_at', 'Canceled At', 'text'),
                $this->field('next_billed_at', 'Next Billed At', 'text'),
                $this->field('payload_content', 'Payload', 'textarea'),
            ],
            'team_accounts' => [
                $this->field('owner_id', 'Owner', 'select', $this->userOptions(), true),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(TeamAccountStatus::cases()), true),
                $this->field('seat_limit', 'Seat Limit', 'number'),
                $this->field('paddle_customer_id', 'Paddle Customer ID', 'text'),
                $this->field('paddle_subscription_id', 'Paddle Subscription ID', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'team_seats' => [
                $this->field('team_account_id', 'Team', 'select', $this->teamAccountOptions(), true),
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('email', 'Email', 'email', required: true),
                $this->field('role', 'Role', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(TeamSeatStatus::cases()), true),
                $this->field('invited_at', 'Invited At', 'text'),
                $this->field('accepted_at', 'Accepted At', 'text'),
            ],
            'payment_reconciliation' => [
                $this->field('provider', 'Provider', 'text', required: true),
                $this->field('event_id', 'Event ID', 'text'),
                $this->field('record_type', 'Record Type', 'text', required: true),
                $this->field('status', 'Status', 'text', required: true),
                $this->field('paddle_transaction_id', 'Paddle Transaction ID', 'text'),
                $this->field('paddle_subscription_id', 'Paddle Subscription ID', 'text'),
                $this->field('paddle_customer_id', 'Paddle Customer ID', 'text'),
                $this->field('payload_content', 'Payload', 'textarea'),
                $this->field('reconciled_at', 'Reconciled At', 'text'),
                $this->field('notes', 'Notes', 'textarea'),
            ],
            'gift_purchases' => [
                $this->field('purchaser_id', 'Purchaser', 'select', $this->userOptions(), true),
                $this->field('recipient_user_id', 'Recipient User', 'select', $this->userOptions()),
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('course_bundle_id', 'Bundle', 'select', $this->courseBundleOptions()),
                $this->field('payment_product_id', 'Product', 'select', $this->paymentProductOptions()),
                $this->field('payment_price_id', 'Plan', 'select', $this->paymentPriceOptions()),
                $this->field('payment_checkout_id', 'Checkout', 'select', $this->paymentCheckoutOptions()),
                $this->field('recipient_email', 'Recipient Email', 'email', required: true),
                $this->field('recipient_name', 'Recipient Name', 'text'),
                $this->field('code', 'Code', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(GiftPurchaseStatus::cases()), true),
                $this->field('message', 'Message', 'textarea'),
                $this->field('purchased_at', 'Purchased At', 'text'),
                $this->field('delivered_at', 'Delivered At', 'text'),
                $this->field('redeemed_at', 'Redeemed At', 'text'),
                $this->field('expires_at', 'Expires At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'affiliate_partners' => [
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('code', 'Code', 'text', required: true),
                $this->field('status', 'Status', 'select', $this->enumOptions(AffiliateStatus::cases()), true),
                $this->field('commission_rate_basis_points', 'Commission Bps', 'number'),
                $this->field('cookie_days', 'Cookie Days', 'number'),
                $this->field('payout_email', 'Payout Email', 'email'),
                $this->field('notes', 'Notes', 'textarea'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'affiliate_visits' => [
                $this->field('affiliate_partner_id', 'Partner', 'select', $this->affiliatePartnerOptions(), true),
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('visitor_id', 'Visitor ID', 'text', required: true),
                $this->field('landing_url', 'Landing URL', 'text'),
                $this->field('referrer_url', 'Referrer URL', 'text'),
                $this->field('clicked_at', 'Clicked At', 'text'),
                $this->field('expires_at', 'Expires At', 'text'),
            ],
            'referral_conversions' => [
                $this->field('affiliate_partner_id', 'Partner', 'select', $this->affiliatePartnerOptions(), true),
                $this->field('affiliate_visit_id', 'Visit', 'select', $this->affiliateVisitOptions()),
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('payment_checkout_id', 'Checkout', 'select', $this->paymentCheckoutOptions()),
                $this->field('payment_order_id', 'Order', 'select', $this->paymentOrderOptions()),
                $this->field('status', 'Status', 'select', $this->enumOptions(ReferralConversionStatus::cases()), true),
                $this->field('amount', 'Amount', 'number'),
                $this->field('commission_amount', 'Commission Amount', 'number'),
                $this->field('converted_at', 'Converted At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'checkout_recoveries' => [
                $this->field('payment_checkout_id', 'Checkout', 'select', $this->paymentCheckoutOptions(), true),
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('email', 'Email', 'email'),
                $this->field('status', 'Status', 'select', $this->enumOptions(CheckoutRecoveryStatus::cases()), true),
                $this->field('recovery_token', 'Recovery Token', 'text', required: true),
                $this->field('recovery_url', 'Recovery URL', 'text'),
                $this->field('reminder_count', 'Reminder Count', 'number'),
                $this->field('last_reminded_at', 'Last Reminded At', 'text'),
                $this->field('recovered_at', 'Recovered At', 'text'),
                $this->field('expires_at', 'Expires At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'lead_magnets' => [
                $this->field('asset_media_id', 'Asset', 'media'),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('slug', 'Slug', 'text'),
                $this->field('description', 'Description', 'textarea'),
                $this->field('status', 'Status', 'select', $this->enumOptions(GrowthStatus::cases()), true),
                $this->field('form_headline', 'Form Headline', 'text'),
                $this->field('delivery_url', 'Delivery URL', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'newsletter_campaigns' => [
                $this->field('lead_magnet_id', 'Lead Magnet', 'select', $this->leadMagnetOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('slug', 'Slug', 'text'),
                $this->field('subject', 'Subject', 'text', required: true),
                $this->field('audience', 'Audience', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(GrowthStatus::cases()), true),
                $this->field('scheduled_at', 'Scheduled At', 'text'),
                $this->field('sent_at', 'Sent At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'lead_submissions' => [
                $this->field('lead_magnet_id', 'Lead Magnet', 'select', $this->leadMagnetOptions(), true),
                $this->field('newsletter_campaign_id', 'Newsletter Campaign', 'select', $this->newsletterCampaignOptions()),
                $this->field('user_id', 'User', 'select', $this->userOptions()),
                $this->field('email', 'Email', 'email', required: true),
                $this->field('name', 'Name', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(LeadSubmissionStatus::cases()), true),
                $this->field('source_url', 'Source URL', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'ab_experiments' => [
                $this->field('key', 'Key', 'text', required: true),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('surface', 'Surface', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(GrowthStatus::cases()), true),
                $this->field('winning_variant_key', 'Winning Variant Key', 'text'),
                $this->field('starts_at', 'Starts At', 'text'),
                $this->field('ends_at', 'Ends At', 'text'),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'ab_variants' => [
                $this->field('ab_experiment_id', 'Experiment', 'select', $this->abExperimentOptions(), true),
                $this->field('key', 'Key', 'text', required: true),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('weight', 'Weight', 'number'),
                $this->field('payload_content', 'Payload', 'textarea'),
            ],
            'social_share_images' => [
                $this->field('shareable_type', 'Shareable Type', 'text', required: true),
                $this->field('shareable_id', 'Shareable ID', 'number', required: true),
                $this->field('media_asset_id', 'Media', 'media'),
                $this->field('image_url', 'Image URL', 'text'),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('alt_text', 'Alt Text', 'text'),
                $this->field('template', 'Template', 'text'),
                $this->field('status', 'Status', 'select', $this->enumOptions(GrowthStatus::cases()), true),
                $this->field('metadata_content', 'Metadata', 'textarea'),
            ],
            'moderation_queue' => [
                $this->field('status', 'Status', 'select', $this->enumOptions(CommunityContentStatus::cases()), true),
                $this->field('assigned_to', 'Assigned To', 'select', $this->userOptions()),
                $this->field('reason', 'Reason', 'text'),
                $this->field('resolution_note', 'Resolution Note', 'textarea'),
            ],
            'content_reports' => [
                $this->field('status', 'Status', 'select', $this->enumOptions(CommunityReportStatus::cases()), true),
                $this->field('reviewed_by', 'Reviewed By', 'select', $this->userOptions()),
                $this->field('reason', 'Reason', 'text', required: true),
                $this->field('details', 'Details', 'textarea'),
                $this->field('resolution_note', 'Resolution Note', 'textarea'),
            ],
            'blocked_words' => [
                $this->field('word', 'Word Or Phrase', 'text', required: true),
                $this->field('match_type', 'Match Type', 'select', [
                    ['label' => 'Contains', 'value' => 'contains'],
                    ['label' => 'Whole Word', 'value' => 'word'],
                    ['label' => 'Exact', 'value' => 'exact'],
                ], true),
                $this->field('severity', 'Severity', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('notes', 'Notes', 'textarea'),
            ],
            'discussion_forums' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('course_category_id', 'Course Category', 'select', $this->courseCategoryOptions()),
                $this->field('created_by', 'Created By', 'select', $this->userOptions()),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('slug', 'Slug', 'text'),
                $this->field('description', 'Description', 'textarea'),
                $this->field('visibility', 'Visibility', 'select', $this->enumOptions(CommunityVisibility::cases()), true),
                $this->field('status', 'Status', 'select', $this->enumOptions(CommunityContentStatus::cases()), true),
                $this->field('sort_order', 'Sort Order', 'number'),
            ],
            'discussion_threads' => [
                $this->field('discussion_forum_id', 'Forum', 'select', $this->discussionForumOptions(), true),
                $this->field('user_id', 'Author', 'select', $this->userOptions()),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('slug', 'Slug', 'text'),
                $this->field('body', 'Body', 'textarea'),
                $this->field('status', 'Status', 'select', $this->enumOptions(CommunityContentStatus::cases()), true),
                $this->field('is_pinned', 'Pinned', 'checkbox'),
                $this->field('is_locked', 'Locked', 'checkbox'),
            ],
            'community_groups' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions()),
                $this->field('created_by', 'Created By', 'select', $this->userOptions()),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('slug', 'Slug', 'text'),
                $this->field('description', 'Description', 'textarea'),
                $this->field('visibility', 'Visibility', 'select', $this->enumOptions(CommunityVisibility::cases()), true),
                $this->field('status', 'Status', 'select', $this->enumOptions(CommunityContentStatus::cases()), true),
                $this->field('requires_paid_access', 'Requires Paid Access', 'checkbox'),
            ],
            'settings' => [
                $this->field('group', 'Group', 'text', required: true),
                $this->field('key', 'Key', 'text', required: true),
                $this->field('value_content', 'Value', 'textarea'),
                $this->field('is_encrypted', 'Encrypted', 'checkbox'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<int, array{label: string, value: string|int}>  $options
     * @return array<string, mixed>
     */
    private function field(string $key, string $label, string $type, array $options = [], bool $required = false): array
    {
        return compact('key', 'label', 'type', 'options', 'required');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filtersFor(string $resource): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->filters($resource);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->filters($resource);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->filters($resource);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->filters($resource);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->filters($resource);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->filters($resource);
        }

        $filters = [
            $this->field('search', 'Search', 'search'),
        ];

        if (in_array($resource, ['users', 'bloggers', 'courses', 'lessons', 'blogs', 'cms', 'media', 'ad_zones', 'ad_campaigns', 'ad_creatives', 'advertiser_requests', 'ad_pricing', 'instructors', 'author_badges', 'editorial_revisions', 'reviewer_comments', 'scheduled_publications', 'revenue_share_rules', 'payment_products', 'payment_prices', 'payment_discounts', 'payment_checkouts', 'payment_orders', 'payment_subscriptions', 'team_accounts', 'team_seats', 'payment_reconciliation', 'gift_purchases', 'affiliate_partners', 'referral_conversions', 'checkout_recoveries', 'lead_magnets', 'newsletter_campaigns', 'lead_submissions', 'ab_experiments', 'social_share_images', 'moderation_queue', 'content_reports', 'blocked_words', 'discussion_forums', 'discussion_threads', 'community_groups', 'home_page_sections'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', match ($resource) {
                'users' => $this->enumOptions(UserStatus::cases()),
                'bloggers' => $this->enumOptions(BloggerStatus::cases()),
                'media' => $this->enumOptions(MediaVisibility::cases()),
                'ad_zones' => $this->enumOptions(AdZoneStatus::cases()),
                'ad_campaigns' => $this->enumOptions(AdCampaignStatus::cases()),
                'ad_creatives' => $this->enumOptions(AdCreativeStatus::cases()),
                'advertiser_requests' => $this->enumOptions(AdvertiserRequestStatus::cases()),
                'ad_pricing', 'payment_prices', 'author_badges', 'blocked_words', 'home_page_sections' => [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ],
                'instructors' => $this->enumOptions(InstructorProfileStatus::cases()),
                'editorial_revisions' => $this->enumOptions(EditorialRevisionStatus::cases()),
                'reviewer_comments' => [
                    ['label' => 'Open', 'value' => 'open'],
                    ['label' => 'Resolved', 'value' => 'resolved'],
                ],
                'scheduled_publications' => $this->enumOptions(ScheduledPublicationStatus::cases()),
                'revenue_share_rules' => $this->enumOptions(RevenueShareRuleStatus::cases()),
                'payment_products' => $this->enumOptions(PaymentProductStatus::cases()),
                'payment_reconciliation' => [
                    ['label' => 'Pending', 'value' => 'pending'],
                    ['label' => 'Recorded', 'value' => 'recorded'],
                    ['label' => 'Reconciled', 'value' => 'reconciled'],
                    ['label' => 'Skipped', 'value' => 'skipped'],
                    ['label' => 'Failed', 'value' => 'failed'],
                ],
                'payment_discounts', 'lead_magnets', 'newsletter_campaigns', 'ab_experiments', 'social_share_images' => $this->enumOptions(GrowthStatus::cases()),
                'payment_checkouts' => $this->enumOptions(PaymentCheckoutStatus::cases()),
                'payment_orders' => $this->enumOptions(PaymentOrderStatus::cases()),
                'payment_subscriptions' => $this->enumOptions(PaymentSubscriptionStatus::cases()),
                'team_accounts' => $this->enumOptions(TeamAccountStatus::cases()),
                'team_seats' => $this->enumOptions(TeamSeatStatus::cases()),
                'gift_purchases' => $this->enumOptions(GiftPurchaseStatus::cases()),
                'affiliate_partners' => $this->enumOptions(AffiliateStatus::cases()),
                'referral_conversions' => $this->enumOptions(ReferralConversionStatus::cases()),
                'checkout_recoveries' => $this->enumOptions(CheckoutRecoveryStatus::cases()),
                'lead_submissions' => $this->enumOptions(LeadSubmissionStatus::cases()),
                'moderation_queue', 'discussion_forums', 'discussion_threads', 'community_groups' => $this->enumOptions(CommunityContentStatus::cases()),
                'content_reports' => $this->enumOptions(CommunityReportStatus::cases()),
                'blogs' => $this->blogWorkflowStatusOptions(),
                default => $this->standardPublishStatusOptions(),
            });
        }

        if ($resource === 'users') {
            $filters[] = $this->field('role', 'Role', 'select', $this->roleOptions());
        }

        if ($resource === 'courses') {
            $filters[] = $this->field('category', 'Category', 'select', $this->courseCategoryOptions());
        }

        if ($resource === 'blogs') {
            $filters[] = $this->field('category', 'Category', 'select', $this->blogCategoryOptions());
        }

        if ($resource === 'trash') {
            $filters[] = $this->field('category', 'Type', 'select', [
                ['label' => 'Courses', 'value' => 'course'],
                ['label' => 'Blogs', 'value' => 'blog'],
            ]);
        }

        if ($resource === 'lessons') {
            $filters[] = $this->field('category', 'Course', 'select', $this->courseOptions());
        }

        if ($resource === 'settings') {
            $filters[] = $this->field('group', 'Group', 'select', $this->settingGroupOptions());
        }

        if (in_array($resource, ['ad_creatives', 'ad_pricing', 'advertiser_requests'], true)) {
            $filters[] = $this->field('category', 'Zone', 'select', $this->adZoneOptions());
        }

        if (in_array($resource, ['editorial_revisions', 'reviewer_comments', 'scheduled_publications'], true)) {
            $filters[] = $this->field('category', 'User', 'select', $this->userOptions());
        }

        if ($resource === 'revenue_share_rules') {
            $filters[] = $this->field('category', 'Course', 'select', $this->courseOptions());
        }

        if ($resource === 'payment_products') {
            $filters[] = $this->field('category', 'Type', 'select', $this->enumOptions(PaymentProductType::cases()));
        }

        if (in_array($resource, ['payment_prices', 'payment_orders', 'payment_subscriptions'], true)) {
            $filters[] = $this->field('category', 'Product', 'select', $this->paymentProductOptions());
        }

        if (in_array($resource, ['payment_discounts', 'gift_purchases'], true)) {
            $filters[] = $this->field('category', 'Product', 'select', $this->paymentProductOptions());
        }

        if (in_array($resource, ['affiliate_visits', 'referral_conversions'], true)) {
            $filters[] = $this->field('category', 'Affiliate Partner', 'select', $this->affiliatePartnerOptions());
        }

        if (in_array($resource, ['newsletter_campaigns', 'lead_submissions'], true)) {
            $filters[] = $this->field('category', 'Lead Magnet', 'select', $this->leadMagnetOptions());
        }

        if ($resource === 'ab_variants') {
            $filters[] = $this->field('category', 'Experiment', 'select', $this->abExperimentOptions());
        }

        if (in_array($resource, ['discussion_forums', 'community_groups'], true)) {
            $filters[] = $this->field('category', 'Course', 'select', $this->courseOptions());
        }

        if ($resource === 'discussion_threads') {
            $filters[] = $this->field('category', 'Forum', 'select', $this->discussionForumOptions());
        }

        if ($resource === 'payment_checkouts') {
            $filters[] = $this->field('category', 'Plan', 'select', $this->paymentPriceOptions());
        }

        if ($resource === 'team_seats') {
            $filters[] = $this->field('category', 'Team', 'select', $this->teamAccountOptions());
        }

        return $filters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bulkActionsFor(string $resource): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->bulkActions($resource);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->bulkActions($resource);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->bulkActions($resource);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->bulkActions($resource);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->bulkActions($resource);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->bulkActions($resource);
        }

        return match ($resource) {
            'users' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(UserStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'bloggers' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(BloggerStatus::cases()), true),
            ],
            'blogs' => [
                $this->bulkAction('status', 'Set Status', $this->blogWorkflowStatusOptions(), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'courses', 'lessons', 'cms' => [
                $this->bulkAction('status', 'Set Status', $this->standardPublishStatusOptions(), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'media' => [
                $this->bulkAction('visibility', 'Set Visibility', $this->enumOptions(MediaVisibility::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'roles', 'permissions', 'menus', 'home_hero_slides', 'settings' => [
                $this->bulkAction('delete', 'Delete'),
            ],
            'home_page_sections' => [
                $this->bulkAction('active', 'Set Active State', [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'ad_zones' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(AdZoneStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'ad_campaigns' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(AdCampaignStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'ad_creatives' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(AdCreativeStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'advertiser_requests' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(AdvertiserRequestStatus::cases()), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'ad_pricing' => [
                $this->bulkAction('active', 'Set Active State', [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'instructors' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(InstructorProfileStatus::cases()), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'author_badges' => [
                $this->bulkAction('active', 'Set Active State', [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'editorial_revisions' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(EditorialRevisionStatus::cases()), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'reviewer_comments' => [
                $this->bulkAction('resolved', 'Set Resolved State', [
                    ['label' => 'Resolved', 'value' => 'resolved'],
                    ['label' => 'Open', 'value' => 'open'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'scheduled_publications' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(ScheduledPublicationStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'revenue_share_rules' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(RevenueShareRuleStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_products' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(PaymentProductStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_discounts', 'lead_magnets', 'newsletter_campaigns', 'ab_experiments', 'social_share_images' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(GrowthStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_prices' => [
                $this->bulkAction('active', 'Set Active State', [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_checkouts' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(PaymentCheckoutStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_orders' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(PaymentOrderStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_subscriptions' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(PaymentSubscriptionStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'team_accounts' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(TeamAccountStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'team_seats' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(TeamSeatStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'payment_reconciliation' => [
                $this->bulkAction('delete', 'Delete'),
            ],
            'gift_purchases' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(GiftPurchaseStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'affiliate_partners' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(AffiliateStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'referral_conversions' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(ReferralConversionStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'checkout_recoveries' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(CheckoutRecoveryStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'lead_submissions' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(LeadSubmissionStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            'affiliate_visits', 'ab_variants' => [
                $this->bulkAction('delete', 'Delete'),
            ],
            'moderation_queue' => [
                $this->bulkAction('status', 'Set Decision', $this->enumOptions(CommunityContentStatus::cases()), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'content_reports' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(CommunityReportStatus::cases()), true),
                $this->bulkAction('delete', 'Delete'),
            ],
            'blocked_words' => [
                $this->bulkAction('active', 'Set Active State', [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Inactive', 'value' => 'inactive'],
                ]),
                $this->bulkAction('delete', 'Delete'),
            ],
            'discussion_forums', 'discussion_threads', 'community_groups' => [
                $this->bulkAction('status', 'Set Status', $this->enumOptions(CommunityContentStatus::cases())),
                $this->bulkAction('delete', 'Delete'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<int, array{label: string, value: string|int}>  $options
     * @return array<string, mixed>
     */
    private function bulkAction(string $value, string $label, array $options = [], bool $needsNote = false): array
    {
        return compact('value', 'label', 'options', 'needsNote');
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(string $resource, Model $record): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->row($resource, $record);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->row($resource, $record);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->row($resource, $record);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->row($resource, $record);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->row($resource, $record);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->row($resource, $record);
        }

        return match ($resource) {
            'users' => $this->userRow($record),
            'roles' => $this->roleRow($record),
            'permissions' => $this->permissionRow($record),
            'bloggers' => $this->bloggerRow($record),
            'courses' => $this->courseRow($record),
            'lessons' => $this->lessonRow($record),
            'blogs' => $this->blogRow($record),
            'cms' => $this->pageRow($record),
            'home_hero' => $this->homeHeroSettingRow($record),
            'home_hero_slides' => $this->homeHeroSlideRow($record),
            'home_page_sections' => $this->homePageSectionRow($record),
            'menus' => $this->menuRow($record),
            'media' => $this->mediaRow($record),
            'ad_zones' => $this->adZoneRow($record),
            'ad_campaigns' => $this->adCampaignRow($record),
            'ad_creatives' => $this->adCreativeRow($record),
            'advertiser_requests' => $this->advertiserRequestRow($record),
            'ad_pricing' => $this->adPricingRow($record),
            'instructors' => $this->instructorRow($record),
            'author_badges' => $this->authorBadgeRow($record),
            'editorial_revisions' => $this->editorialRevisionRow($record),
            'reviewer_comments' => $this->reviewerCommentRow($record),
            'scheduled_publications' => $this->scheduledPublicationRow($record),
            'revenue_share_rules' => $this->revenueShareRuleRow($record),
            'payment_products' => $this->paymentProductRow($record),
            'payment_prices' => $this->paymentPriceRow($record),
            'payment_discounts' => $this->paymentDiscountRow($record),
            'payment_checkouts' => $this->paymentCheckoutRow($record),
            'payment_orders' => $this->paymentOrderRow($record),
            'payment_subscriptions' => $this->paymentSubscriptionRow($record),
            'team_accounts' => $this->teamAccountRow($record),
            'team_seats' => $this->teamSeatRow($record),
            'payment_reconciliation' => $this->paymentReconciliationRow($record),
            'gift_purchases' => $this->giftPurchaseRow($record),
            'affiliate_partners' => $this->affiliatePartnerRow($record),
            'affiliate_visits' => $this->affiliateVisitRow($record),
            'referral_conversions' => $this->referralConversionRow($record),
            'checkout_recoveries' => $this->checkoutRecoveryRow($record),
            'lead_magnets' => $this->leadMagnetRow($record),
            'newsletter_campaigns' => $this->newsletterCampaignRow($record),
            'lead_submissions' => $this->leadSubmissionRow($record),
            'ab_experiments' => $this->abExperimentRow($record),
            'ab_variants' => $this->abVariantRow($record),
            'social_share_images' => $this->socialShareImageRow($record),
            'moderation_queue' => $this->moderationQueueRow($record),
            'content_reports' => $this->contentReportRow($record),
            'blocked_words' => $this->blockedWordRow($record),
            'discussion_forums' => $this->discussionForumRow($record),
            'discussion_threads' => $this->discussionThreadRow($record),
            'community_groups' => $this->communityGroupRow($record),
            'settings' => $this->settingRow($record),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(Model $record): array
    {
        /** @var User $record */
        $roles = $record->roles->pluck('name')->values()->all();

        return [
            'id' => $record->id,
            'name' => $record->name,
            'email' => $record->email,
            'status' => $this->enumValue($record->getAttribute('status')),
            'roles' => implode(', ', $roles),
            'created_at' => $record->created_at?->toDateString(),
            'form' => [
                'name' => $record->name,
                'email' => $record->email,
                'password' => '',
                'status' => $this->enumValue($record->getAttribute('status')),
                'roles' => $roles,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRow(Model $record): array
    {
        /** @var Role $record */
        $permissions = $record->permissions->pluck('name')->values()->all();

        return [
            'id' => $record->id,
            'name' => $record->name,
            'permissions_count' => count($permissions),
            'permissions' => implode(', ', array_slice($permissions, 0, 5)),
            'form' => [
                'name' => $record->name,
                'permissions' => $permissions,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionRow(Model $record): array
    {
        /** @var Permission $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'guard_name' => $record->guard_name,
            'form' => [
                'name' => $record->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bloggerRow(Model $record): array
    {
        /** @var BloggerProfile $record */

        return [
            'id' => $record->id,
            'user' => $record->user?->name,
            'email' => $record->user?->email,
            'status' => $this->enumValue($record->getAttribute('status')),
            'expertise' => $record->expertise,
            'reviewed_by' => $record->reviewer?->name,
            'history_count' => $record->approvalHistories->count(),
            'form' => [
                'user_id' => $record->user_id,
                'phone' => $record->phone,
                'expertise' => $record->expertise,
                'bio' => $record->bio,
                'application_reason' => $record->application_reason,
                'status' => $this->enumValue($record->getAttribute('status')),
                'admin_notes' => $record->admin_notes,
            ],
            'history' => $this->historyRows($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseRow(Model $record): array
    {
        /** @var Course $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'status' => $this->enumValue($record->getAttribute('status')),
            'creator' => $record->creator?->name,
            'category' => $record->category?->name,
            'ownership' => $this->courseHasOwnershipProof($record) ? 'Complete' : 'Missing',
            'curriculum' => sprintf(
                '%s sections / %s lessons / %s resources / %s FAQs',
                $record->sections_count ?? 0,
                $record->lessons_count ?? 0,
                $record->resources_count ?? 0,
                $record->faqs_count ?? 0,
            ),
            'price' => (float) $record->price,
            'published_at' => $this->dateString($record->getAttribute('published_at')),
            'form' => Arr::only($record->toArray(), [
                'title',
                'course_category_id',
                'course_subcategory_id',
                'thumbnail_media_id',
                'ownership_video_media_id',
                'ownership_video_url',
                'ownership_statement',
                'short_description',
                'description',
                'level',
                'language',
                'price',
                'is_free',
                'seo_title',
                'seo_description',
                'admin_notes',
                'rejection_reason',
            ]) + ['status' => $this->enumValue($record->getAttribute('status'))],
            'history' => $this->historyRows($record),
            'workflow_actions' => $this->courseWorkflowActions($record),
            'review_items' => $this->courseReviewItems($record),
            'preview_url' => $record->isPublished() ? route('public.courses.show', $record->slug, false) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonRow(Model $record): array
    {
        /** @var CourseLesson $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->getAttribute('status')),
            'order_number' => $record->order_number,
            'is_free' => $record->is_free ? 'Yes' : 'No',
            'published_at' => $this->dateString($record->getAttribute('published_at')),
            'form' => Arr::only($record->toArray(), [
                'course_id',
                'course_section_id',
                'title',
                'order_number',
                'content',
                'video_url',
                'video_file_id',
                'is_free',
                'is_paid',
                'preview_word_limit',
                'seo_title',
                'seo_description',
                'admin_notes',
                'rejection_reason',
            ]) + [
                'status' => $this->enumValue($record->getAttribute('status')),
                'video_type' => $this->enumValue($record->video_type),
            ],
            'history' => $this->historyRows($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blogRow(Model $record): array
    {
        /** @var BlogPost $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'status' => $this->enumValue($record->getAttribute('status')),
            'category' => $record->category?->name,
            'author' => $record->author?->name,
            'is_featured' => $record->is_featured ? 'Yes' : 'No',
            'published_at' => $this->dateString($record->getAttribute('published_at')),
            'form' => Arr::only($record->toArray(), [
                'title',
                'blog_category_id',
                'author_id',
                'featured_image_media_id',
                'excerpt',
                'content',
                'is_featured',
                'seo_title',
                'seo_description',
                'admin_notes',
                'rejection_reason',
            ]) + [
                'status' => $this->enumValue($record->getAttribute('status')),
                'blog_tag_ids' => $record->tags->pluck('id')->values()->all(),
            ],
            'history' => $this->historyRows($record),
            'workflow_actions' => $this->blogWorkflowActions($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageRow(Model $record): array
    {
        /** @var Page $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'status' => $this->enumValue($record->getAttribute('status')),
            'template' => $record->template,
            'author' => $record->author?->name,
            'published_at' => $this->dateString($record->getAttribute('published_at')),
            'form' => Arr::only($record->toArray(), [
                'title',
                'author_id',
                'excerpt',
                'content',
                'template',
                'sort_order',
                'seo_title',
                'seo_description',
                'seo_image',
                'admin_notes',
                'rejection_reason',
            ]) + ['status' => $this->enumValue($record->getAttribute('status'))],
            'history' => $this->historyRows($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function homeHeroSettingRow(Model $record): array
    {
        /** @var SiteSetting $record */
        $rawValue = $record->getAttribute('value');
        $value = is_array($rawValue) ? $rawValue : [];
        $mode = HomeHeroMode::tryFrom((string) ($value['mode'] ?? '')) ?? HomeHeroMode::Design;
        $highlightTerms = $this->homeHeroHighlightTerms($value['highlight_terms'] ?? ['courses', 'mentors']);

        return [
            'id' => $record->id,
            'mode' => str($mode->value)->headline()->toString(),
            'eyebrow' => $value['eyebrow'] ?? null,
            'heading' => $value['heading'] ?? null,
            'search_enabled' => $this->truthyLabel($value['search_enabled'] ?? true),
            'featured_course_enabled' => $this->truthyLabel($value['featured_course_enabled'] ?? true),
            'form' => [
                'mode' => $mode->value,
                'eyebrow' => $value['eyebrow'] ?? 'The leader in online learning',
                'heading' => $value['heading'] ?? 'Find the best courses from expert mentors.',
                'highlight_terms' => implode(', ', $highlightTerms),
                'description' => $value['description'] ?? '',
                'search_enabled' => $this->truthyValue($value['search_enabled'] ?? true),
                'stats_enabled' => $this->truthyValue($value['stats_enabled'] ?? true),
                'featured_course_enabled' => $this->truthyValue($value['featured_course_enabled'] ?? true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function homeHeroSlideRow(Model $record): array
    {
        /** @var HomeHeroSlide $record */
        $imageUrl = $record->mediaAsset?->url ?: $record->image_url;

        return [
            'id' => $record->id,
            'title' => $record->title,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'target_url' => $record->target_url,
            'button_label' => $record->button_label,
            'sort_order' => $record->sort_order,
            'image_url' => $imageUrl,
            'preview_url' => $imageUrl,
            'form' => Arr::only($record->toArray(), [
                'media_asset_id',
                'image_url',
                'image_alt',
                'eyebrow',
                'title',
                'subtitle',
                'button_label',
                'target_url',
                'text_position',
                'sort_order',
                'is_active',
                'opens_in_new_tab',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function homePageSectionRow(Model $record): array
    {
        /** @var HomePageSection $record */

        return [
            'id' => $record->id,
            'key' => $record->key,
            'type' => $this->enumValue($record->getAttribute('type')),
            'title' => $record->title,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'sort_order' => $record->sort_order,
            'cta_url' => $record->cta_url,
            'form' => Arr::only($record->toArray(), [
                'key',
                'eyebrow',
                'title',
                'subtitle',
                'body',
                'cta_label',
                'cta_url',
                'background',
                'sort_order',
                'is_active',
            ]) + [
                'type' => $this->enumValue($record->getAttribute('type')),
                'payload_content' => $this->jsonContent($record->payload),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function menuRow(Model $record): array
    {
        /** @var Menu $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'location' => $record->location,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'items_count' => $record->items_count ?? null,
            'form' => [
                'name' => $record->name,
                'location' => $record->location,
                'is_active' => $record->is_active,
            ],
            'workflow_actions' => [
                $this->workflowAction('Build Menu', route('admin.menus.builder.show', $record, false), 'default', 'get'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaRow(Model $record): array
    {
        /** @var MediaAsset $record */

        return [
            'id' => $record->id,
            'title' => $record->title ?: basename($record->path),
            'visibility' => $this->enumValue($record->visibility),
            'mime_type' => $record->mime_type,
            'dimensions' => $record->width && $record->height ? "{$record->width}x{$record->height}" : null,
            'path' => $record->path,
            'usages_count' => $record->usages_count ?? $record->usages->count(),
            'preview_url' => $record->url,
            'form' => Arr::only($record->toArray(), [
                'folder_id',
                'uploaded_by',
                'title',
                'path',
                'url',
                'alt_text',
                'caption',
                'mime_type',
                'size',
                'width',
                'height',
            ]) + ['visibility' => $this->enumValue($record->visibility)],
            'review_items' => $record->usages->take(8)->map(fn ($usage): array => [
                'label' => $usage->collection ?: 'Usage',
                'value' => class_basename((string) $usage->mediable_type).' #'.$usage->mediable_id,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingRow(Model $record): array
    {
        /** @var SiteSetting $record */
        $value = $record->getAttribute('value');

        return [
            'id' => $record->id,
            'group' => $record->group,
            'key' => $record->key,
            'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : null,
            'is_encrypted' => $record->is_encrypted ? 'Yes' : 'No',
            'form' => [
                'group' => $record->group,
                'key' => $record->key,
                'value_content' => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
                'is_encrypted' => $record->is_encrypted,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adZoneRow(Model $record): array
    {
        /** @var AdZone $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'location' => $record->location,
            'status' => $this->enumValue($record->status),
            'dimensions' => $record->width && $record->height ? "{$record->width}x{$record->height}" : null,
            'creatives_count' => $record->creatives_count ?? 0,
            'impressions_count' => $record->impressions_count ?? 0,
            'clicks_count' => $record->clicks_count ?? 0,
            'form' => Arr::only($record->toArray(), [
                'name',
                'location',
                'description',
                'width',
                'height',
                'max_creatives',
            ]) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adCampaignRow(Model $record): array
    {
        /** @var AdCampaign $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'advertiser_name' => $record->advertiser_name,
            'status' => $this->enumValue($record->status),
            'pricing_model' => $this->enumValue($record->pricing_model),
            'budget_total' => $record->budget_total,
            'starts_at' => $this->dateString($record->getAttribute('starts_at')),
            'ends_at' => $this->dateString($record->getAttribute('ends_at')),
            'form' => Arr::only($record->toArray(), [
                'name',
                'advertiser_name',
                'advertiser_email',
                'currency',
                'budget_total',
                'daily_budget',
                'cpm_rate',
                'cpc_rate',
                'flat_rate',
                'target_url',
                'notes',
            ]) + [
                'status' => $this->enumValue($record->status),
                'pricing_model' => $this->enumValue($record->pricing_model),
                'starts_at' => $this->dateTimeString($record->getAttribute('starts_at')),
                'ends_at' => $this->dateTimeString($record->getAttribute('ends_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adCreativeRow(Model $record): array
    {
        /** @var AdCreative $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'campaign' => $record->campaign?->name,
            'zone' => $record->zone?->name,
            'type' => $this->enumValue($record->type),
            'status' => $this->enumValue($record->status),
            'impressions_count' => $record->impressions_count ?? 0,
            'clicks_count' => $record->clicks_count ?? 0,
            'preview_url' => $record->mediaAsset?->url,
            'form' => Arr::only($record->toArray(), [
                'ad_campaign_id',
                'ad_zone_id',
                'media_asset_id',
                'name',
                'headline',
                'body',
                'cta_text',
                'target_url',
                'html_snippet',
                'weight',
            ]) + [
                'type' => $this->enumValue($record->type),
                'status' => $this->enumValue($record->status),
                'starts_at' => $this->dateTimeString($record->getAttribute('starts_at')),
                'ends_at' => $this->dateTimeString($record->getAttribute('ends_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function advertiserRequestRow(Model $record): array
    {
        /** @var AdvertiserRequest $record */

        return [
            'id' => $record->id,
            'company_name' => $record->company_name,
            'contact_name' => $record->contact_name,
            'email' => $record->email,
            'status' => $this->enumValue($record->status),
            'requested_zone' => $record->requestedZone?->name,
            'budget_range' => trim(($record->budget_min ?: '0').' - '.($record->budget_max ?: '0')),
            'form' => Arr::only($record->toArray(), [
                'requested_ad_zone_id',
                'company_name',
                'contact_name',
                'email',
                'phone',
                'website_url',
                'budget_min',
                'budget_max',
                'message',
                'admin_notes',
            ]) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adPricingRow(Model $record): array
    {
        /** @var AdPricingSetting $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'zone' => $record->zone?->name ?: 'Global',
            'pricing_model' => $this->enumValue($record->pricing_model),
            'currency' => $record->currency,
            'cpm_rate' => $record->cpm_rate,
            'cpc_rate' => $record->cpc_rate,
            'flat_rate' => $record->flat_rate,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'form' => Arr::only($record->toArray(), [
                'ad_zone_id',
                'name',
                'currency',
                'cpm_rate',
                'cpc_rate',
                'flat_rate',
                'min_spend',
                'is_active',
                'notes',
            ]) + [
                'pricing_model' => $this->enumValue($record->pricing_model),
                'effective_from' => $this->dateTimeString($record->getAttribute('effective_from')),
                'effective_until' => $this->dateTimeString($record->getAttribute('effective_until')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instructorRow(Model $record): array
    {
        /** @var InstructorProfile $record */

        return [
            'id' => $record->id,
            'display_name' => $record->display_name,
            'user' => $record->user?->email,
            'status' => $this->enumValue($record->getAttribute('status')),
            'expertise' => $record->expertise,
            'is_verified_expert' => $record->is_verified_expert ? 'Yes' : 'No',
            'accepts_revenue_share' => $record->accepts_revenue_share ? 'Yes' : 'No',
            'preview_url' => $record->avatar?->url,
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'avatar_media_id',
                'display_name',
                'headline',
                'bio',
                'credentials',
                'expertise',
                'website_url',
                'linkedin_url',
                'is_verified_expert',
                'accepts_revenue_share',
                'payout_currency',
                'payout_account_reference',
                'admin_notes',
            ]) + [
                'status' => $this->enumValue($record->getAttribute('status')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
            'history' => $this->historyRows($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function authorBadgeRow(Model $record): array
    {
        /** @var AuthorBadge $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'marks_verified_expert' => $record->marks_verified_expert ? 'Yes' : 'No',
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'users_count' => $record->users_count ?? 0,
            'form' => Arr::only($record->toArray(), [
                'name',
                'description',
                'icon',
                'color',
                'marks_verified_expert',
                'is_active',
                'sort_order',
            ]) + [
                'user_ids' => $record->users()->pluck('users.id')->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorialRevisionRow(Model $record): array
    {
        /** @var EditorialRevision $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'status' => $this->enumValue($record->getAttribute('status')),
            'author' => $record->author?->name,
            'reviewer' => $record->reviewer?->name,
            'comments_count' => $record->comments_count ?? $record->comments->count(),
            'scheduled_at' => $this->dateTimeString($record->getAttribute('scheduled_at')),
            'form' => Arr::only($record->toArray(), [
                'author_id',
                'reviewer_id',
                'title',
                'summary',
            ]) + [
                'payload_content' => $this->jsonContent($record->payload),
                'status' => $this->enumValue($record->getAttribute('status')),
                'submitted_at' => $this->dateTimeString($record->getAttribute('submitted_at')),
                'reviewed_at' => $this->dateTimeString($record->getAttribute('reviewed_at')),
                'scheduled_at' => $this->dateTimeString($record->getAttribute('scheduled_at')),
            ],
            'history' => $this->historyRows($record),
            'workflow_actions' => [
                ...$this->editorialRevisionWorkflowActions($record),
                $this->workflowAction('Compare', route('admin.editorial.revisions.review', $record, false), 'default', 'get'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewerCommentRow(Model $record): array
    {
        /** @var ReviewerComment $record */

        return [
            'id' => $record->id,
            'revision' => $record->revision?->title,
            'reviewer' => $record->reviewer?->name,
            'field_path' => $record->field_path,
            'is_resolved' => $record->is_resolved ? 'Yes' : 'No',
            'created_at' => $record->created_at?->toDateString(),
            'form' => Arr::only($record->toArray(), [
                'editorial_revision_id',
                'reviewer_id',
                'field_path',
                'body',
                'is_resolved',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduledPublicationRow(Model $record): array
    {
        /** @var ScheduledPublication $record */

        return [
            'id' => $record->id,
            'publishable' => class_basename((string) $record->publishable_type).' #'.$record->publishable_id,
            'status' => $this->enumValue($record->getAttribute('status')),
            'publish_at' => $this->dateTimeString($record->getAttribute('publish_at')),
            'creator' => $record->creator?->name,
            'approved_by' => $record->approver?->name,
            'published_at' => $this->dateTimeString($record->getAttribute('published_at')),
            'form' => Arr::only($record->toArray(), [
                'editorial_revision_id',
                'created_by',
                'approved_by',
                'timezone',
            ]) + [
                'publish_at' => $this->dateTimeString($record->getAttribute('publish_at')),
                'status' => $this->enumValue($record->getAttribute('status')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueShareRuleRow(Model $record): array
    {
        /** @var RevenueShareRule $record */

        return [
            'id' => $record->id,
            'user' => $record->user?->email,
            'course' => $record->course?->title,
            'type' => $this->enumValue($record->getAttribute('type')),
            'status' => $this->enumValue($record->getAttribute('status')),
            'share_percent' => (float) $record->share_percent,
            'currency' => $record->currency,
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'instructor_profile_id',
                'course_id',
                'payment_product_id',
                'share_percent',
                'fixed_amount_cents',
                'currency',
                'notes',
            ]) + [
                'type' => $this->enumValue($record->getAttribute('type')),
                'status' => $this->enumValue($record->getAttribute('status')),
                'starts_at' => $this->dateTimeString($record->getAttribute('starts_at')),
                'ends_at' => $this->dateTimeString($record->getAttribute('ends_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentProductRow(Model $record): array
    {
        /** @var PaymentProduct $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'type' => $this->enumValue($record->type),
            'status' => $this->enumValue($record->status),
            'course' => $record->course?->title,
            'bundle' => $record->bundle?->title,
            'paddle_product_id' => $record->paddle_product_id,
            'prices_count' => $record->prices_count ?? $record->prices->count(),
            'form' => Arr::only($record->toArray(), [
                'course_id',
                'course_bundle_id',
                'name',
                'description',
                'paddle_product_id',
                'tax_category',
            ]) + [
                'type' => $this->enumValue($record->type),
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPriceRow(Model $record): array
    {
        /** @var PaymentPrice $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'product' => $record->product?->name,
            'billing_interval' => $this->enumValue($record->billing_interval),
            'amount' => $record->formattedAmount(),
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'paddle_price_id' => $record->paddle_price_id,
            'form' => Arr::only($record->toArray(), [
                'payment_product_id',
                'name',
                'paddle_price_id',
                'is_recurring',
                'currency',
                'amount',
                'trial_days',
                'seat_min',
                'seat_max',
                'is_active',
            ]) + [
                'billing_interval' => $this->enumValue($record->billing_interval),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentCheckoutRow(Model $record): array
    {
        /** @var PaymentCheckout $record */

        return [
            'id' => $record->id,
            'user' => $record->user?->email,
            'product' => $record->price?->product?->name,
            'status' => $this->enumValue($record->status),
            'quantity' => $record->quantity,
            'discount' => $record->discount?->code,
            'paddle_transaction_id' => $record->paddle_transaction_id,
            'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'payment_price_id',
                'course_id',
                'team_account_id',
                'payment_discount_id',
                'quantity',
                'discount_amount',
                'paddle_transaction_id',
                'checkout_url',
                'recovery_email',
            ]) + [
                'status' => $this->enumValue($record->status),
                'custom_data_content' => $this->jsonContent($record->custom_data),
                'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentOrderRow(Model $record): array
    {
        /** @var PaymentOrder $record */

        return [
            'id' => $record->id,
            'user' => $record->user?->email,
            'status' => $this->enumValue($record->status),
            'total' => $this->moneyString($record->currency, $record->total),
            'paddle_transaction_id' => $record->paddle_transaction_id,
            'paddle_subscription_id' => $record->paddle_subscription_id,
            'purchased_at' => $this->dateTimeString($record->getAttribute('purchased_at')),
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'team_account_id',
                'payment_checkout_id',
                'provider',
                'paddle_transaction_id',
                'paddle_customer_id',
                'paddle_subscription_id',
                'currency',
                'subtotal',
                'tax',
                'discount',
                'total',
            ]) + [
                'status' => $this->enumValue($record->status),
                'purchased_at' => $this->dateTimeString($record->getAttribute('purchased_at')),
                'payload_content' => $this->jsonContent($record->payload),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSubscriptionRow(Model $record): array
    {
        /** @var PaymentSubscription $record */

        return [
            'id' => $record->id,
            'user' => $record->user?->email,
            'product' => $record->product?->name,
            'status' => $this->enumValue($record->status),
            'quantity' => $record->quantity,
            'paddle_subscription_id' => $record->paddle_subscription_id,
            'next_billed_at' => $this->dateTimeString($record->getAttribute('next_billed_at')),
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'team_account_id',
                'payment_product_id',
                'payment_price_id',
                'provider',
                'paddle_subscription_id',
                'paddle_customer_id',
                'currency',
                'quantity',
            ]) + [
                'status' => $this->enumValue($record->status),
                'current_period_starts_at' => $this->dateTimeString($record->getAttribute('current_period_starts_at')),
                'current_period_ends_at' => $this->dateTimeString($record->getAttribute('current_period_ends_at')),
                'trial_ends_at' => $this->dateTimeString($record->getAttribute('trial_ends_at')),
                'canceled_at' => $this->dateTimeString($record->getAttribute('canceled_at')),
                'next_billed_at' => $this->dateTimeString($record->getAttribute('next_billed_at')),
                'payload_content' => $this->jsonContent($record->payload),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamAccountRow(Model $record): array
    {
        /** @var TeamAccount $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'owner' => $record->owner?->email,
            'status' => $this->enumValue($record->status),
            'seat_limit' => $record->seat_limit,
            'seats_count' => $record->seats_count ?? 0,
            'paddle_subscription_id' => $record->paddle_subscription_id,
            'form' => Arr::only($record->toArray(), [
                'owner_id',
                'name',
                'seat_limit',
                'paddle_customer_id',
                'paddle_subscription_id',
            ]) + [
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamSeatRow(Model $record): array
    {
        /** @var TeamSeat $record */

        return [
            'id' => $record->id,
            'email' => $record->email,
            'team' => $record->teamAccount?->name,
            'user' => $record->user?->email,
            'role' => $record->role,
            'status' => $this->enumValue($record->status),
            'accepted_at' => $this->dateTimeString($record->getAttribute('accepted_at')),
            'form' => Arr::only($record->toArray(), [
                'team_account_id',
                'user_id',
                'email',
                'role',
            ]) + [
                'status' => $this->enumValue($record->status),
                'invited_at' => $this->dateTimeString($record->getAttribute('invited_at')),
                'accepted_at' => $this->dateTimeString($record->getAttribute('accepted_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentReconciliationRow(Model $record): array
    {
        /** @var PaymentReconciliationRecord $record */

        return [
            'id' => $record->id,
            'record_type' => $record->record_type,
            'status' => $record->status,
            'event_id' => $record->event_id,
            'paddle_transaction_id' => $record->paddle_transaction_id,
            'paddle_subscription_id' => $record->paddle_subscription_id,
            'reconciled_at' => $this->dateTimeString($record->getAttribute('reconciled_at')),
            'form' => Arr::only($record->toArray(), [
                'provider',
                'event_id',
                'record_type',
                'status',
                'paddle_transaction_id',
                'paddle_subscription_id',
                'paddle_customer_id',
                'notes',
            ]) + [
                'payload_content' => $this->jsonContent($record->payload),
                'reconciled_at' => $this->dateTimeString($record->getAttribute('reconciled_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentDiscountRow(Model $record): array
    {
        /** @var PaymentDiscount $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'code' => $record->code,
            'type' => $this->enumValue($record->type),
            'value' => $this->enumValue($record->type) === DiscountType::Percent->value ? $record->value.'%' : $this->moneyString($record->currency, (int) $record->value),
            'status' => $this->enumValue($record->status),
            'redemptions_count' => $record->redemptions_count ?? 0,
            'form' => Arr::only($record->toArray(), [
                'payment_product_id',
                'payment_price_id',
                'course_id',
                'name',
                'code',
                'description',
                'value',
                'currency',
                'is_launch_offer',
                'paddle_discount_id',
                'max_redemptions',
                'per_user_limit',
            ]) + [
                'type' => $this->enumValue($record->type),
                'status' => $this->enumValue($record->status),
                'starts_at' => $this->dateTimeString($record->getAttribute('starts_at')),
                'ends_at' => $this->dateTimeString($record->getAttribute('ends_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function giftPurchaseRow(Model $record): array
    {
        /** @var GiftPurchase $record */

        return [
            'id' => $record->id,
            'recipient_email' => $record->recipient_email,
            'status' => $this->enumValue($record->status),
            'product' => $record->product?->name,
            'course' => $record->course?->title,
            'code' => $record->code,
            'purchased_at' => $this->dateTimeString($record->getAttribute('purchased_at')),
            'form' => Arr::only($record->toArray(), [
                'purchaser_id',
                'recipient_user_id',
                'course_id',
                'course_bundle_id',
                'payment_product_id',
                'payment_price_id',
                'payment_checkout_id',
                'recipient_email',
                'recipient_name',
                'code',
                'message',
            ]) + [
                'status' => $this->enumValue($record->status),
                'purchased_at' => $this->dateTimeString($record->getAttribute('purchased_at')),
                'delivered_at' => $this->dateTimeString($record->getAttribute('delivered_at')),
                'redeemed_at' => $this->dateTimeString($record->getAttribute('redeemed_at')),
                'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliatePartnerRow(Model $record): array
    {
        /** @var AffiliatePartner $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'code' => $record->code,
            'status' => $this->enumValue($record->status),
            'commission_rate' => number_format($record->commission_rate_basis_points / 100, 2).'%',
            'visits_count' => $record->visits_count ?? 0,
            'conversions_count' => $record->conversions_count ?? 0,
            'form' => Arr::only($record->toArray(), [
                'user_id',
                'name',
                'code',
                'commission_rate_basis_points',
                'cookie_days',
                'payout_email',
                'notes',
            ]) + [
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliateVisitRow(Model $record): array
    {
        /** @var AffiliateVisit $record */

        return [
            'id' => $record->id,
            'partner' => $record->partner?->code,
            'visitor_id' => $record->visitor_id,
            'landing_url' => $record->landing_url,
            'clicked_at' => $this->dateTimeString($record->getAttribute('clicked_at')),
            'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
            'form' => Arr::only($record->toArray(), [
                'affiliate_partner_id',
                'user_id',
                'visitor_id',
                'landing_url',
                'referrer_url',
            ]) + [
                'clicked_at' => $this->dateTimeString($record->getAttribute('clicked_at')),
                'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referralConversionRow(Model $record): array
    {
        /** @var ReferralConversion $record */

        return [
            'id' => $record->id,
            'partner' => $record->partner?->code,
            'user' => $record->user?->email,
            'status' => $this->enumValue($record->status),
            'amount' => $this->moneyString('USD', (int) $record->amount),
            'commission_amount' => $this->moneyString('USD', (int) $record->commission_amount),
            'converted_at' => $this->dateTimeString($record->getAttribute('converted_at')),
            'form' => Arr::only($record->toArray(), [
                'affiliate_partner_id',
                'affiliate_visit_id',
                'user_id',
                'payment_checkout_id',
                'payment_order_id',
                'amount',
                'commission_amount',
            ]) + [
                'status' => $this->enumValue($record->status),
                'converted_at' => $this->dateTimeString($record->getAttribute('converted_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutRecoveryRow(Model $record): array
    {
        /** @var AbandonedCheckoutRecovery $record */

        return [
            'id' => $record->id,
            'email' => $record->email,
            'product' => $record->checkout?->price?->product?->name,
            'status' => $this->enumValue($record->status),
            'reminder_count' => $record->reminder_count,
            'recovered_at' => $this->dateTimeString($record->getAttribute('recovered_at')),
            'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
            'form' => Arr::only($record->toArray(), [
                'payment_checkout_id',
                'user_id',
                'email',
                'recovery_token',
                'recovery_url',
                'reminder_count',
            ]) + [
                'status' => $this->enumValue($record->status),
                'last_reminded_at' => $this->dateTimeString($record->getAttribute('last_reminded_at')),
                'recovered_at' => $this->dateTimeString($record->getAttribute('recovered_at')),
                'expires_at' => $this->dateTimeString($record->getAttribute('expires_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadMagnetRow(Model $record): array
    {
        /** @var LeadMagnet $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'slug' => $record->slug,
            'status' => $this->enumValue($record->status),
            'submissions_count' => $record->submissions_count ?? 0,
            'delivery_url' => $record->delivery_url,
            'form' => Arr::only($record->toArray(), [
                'asset_media_id',
                'title',
                'slug',
                'description',
                'form_headline',
                'delivery_url',
            ]) + [
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newsletterCampaignRow(Model $record): array
    {
        /** @var NewsletterCampaign $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'subject' => $record->subject,
            'status' => $this->enumValue($record->status),
            'lead_magnet' => $record->leadMagnet?->title,
            'scheduled_at' => $this->dateTimeString($record->getAttribute('scheduled_at')),
            'sent_at' => $this->dateTimeString($record->getAttribute('sent_at')),
            'form' => Arr::only($record->toArray(), [
                'lead_magnet_id',
                'name',
                'slug',
                'subject',
                'audience',
            ]) + [
                'status' => $this->enumValue($record->status),
                'scheduled_at' => $this->dateTimeString($record->getAttribute('scheduled_at')),
                'sent_at' => $this->dateTimeString($record->getAttribute('sent_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadSubmissionRow(Model $record): array
    {
        /** @var LeadSubmission $record */

        return [
            'id' => $record->id,
            'email' => $record->email,
            'name' => $record->name,
            'status' => $this->enumValue($record->status),
            'lead_magnet' => $record->leadMagnet?->title,
            'created_at' => $record->created_at?->toDateString(),
            'form' => Arr::only($record->toArray(), [
                'lead_magnet_id',
                'newsletter_campaign_id',
                'user_id',
                'email',
                'name',
                'source_url',
            ]) + [
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function abExperimentRow(Model $record): array
    {
        /** @var AbExperiment $record */

        return [
            'id' => $record->id,
            'key' => $record->key,
            'name' => $record->name,
            'surface' => $record->surface,
            'status' => $this->enumValue($record->status),
            'variants_count' => $record->variants_count ?? 0,
            'winning_variant_key' => $record->winning_variant_key,
            'form' => Arr::only($record->toArray(), [
                'key',
                'name',
                'surface',
                'winning_variant_key',
            ]) + [
                'status' => $this->enumValue($record->status),
                'starts_at' => $this->dateTimeString($record->getAttribute('starts_at')),
                'ends_at' => $this->dateTimeString($record->getAttribute('ends_at')),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function abVariantRow(Model $record): array
    {
        /** @var AbVariant $record */

        return [
            'id' => $record->id,
            'experiment' => $record->experiment?->key,
            'key' => $record->key,
            'name' => $record->name,
            'weight' => $record->weight,
            'views_count' => $record->views_count,
            'conversions_count' => $record->conversions_count,
            'form' => Arr::only($record->toArray(), [
                'ab_experiment_id',
                'key',
                'name',
                'weight',
            ]) + [
                'payload_content' => $this->jsonContent($record->payload),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function socialShareImageRow(Model $record): array
    {
        /** @var SocialShareImage $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'shareable' => $this->morphLabel($record->shareable_type, $record->shareable_id),
            'template' => $record->template,
            'status' => $this->enumValue($record->status),
            'image_url' => $record->image_url ?: $record->media?->url,
            'preview_url' => $record->image_url ?: $record->media?->url,
            'form' => Arr::only($record->toArray(), [
                'shareable_type',
                'shareable_id',
                'media_asset_id',
                'image_url',
                'title',
                'alt_text',
                'template',
            ]) + [
                'status' => $this->enumValue($record->status),
                'metadata_content' => $this->jsonContent($record->metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moderationQueueRow(Model $record): array
    {
        /** @var ModerationQueueItem $record */

        return [
            'id' => $record->id,
            'subject' => $this->morphLabel($record->subject_type, $record->subject_id),
            'status' => $this->enumValue($record->status),
            'reason' => $record->reason,
            'spam_score' => $record->spam_score,
            'reporter' => $record->reporter?->email,
            'reviewed_at' => $this->dateTimeString($record->getAttribute('reviewed_at')),
            'form' => [
                'status' => $this->enumValue($record->status),
                'assigned_to' => $record->assigned_to,
                'reason' => $record->reason,
                'resolution_note' => $record->resolution_note,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentReportRow(Model $record): array
    {
        /** @var ContentReport $record */

        return [
            'id' => $record->id,
            'reportable' => $this->morphLabel($record->reportable_type, $record->reportable_id),
            'status' => $this->enumValue($record->status),
            'reason' => $record->reason,
            'reporter' => $record->reporter?->email,
            'reviewed_by' => $record->reviewer?->email,
            'reviewed_at' => $this->dateTimeString($record->getAttribute('reviewed_at')),
            'form' => [
                'status' => $this->enumValue($record->status),
                'reviewed_by' => $record->reviewed_by,
                'reason' => $record->reason,
                'details' => $record->details,
                'resolution_note' => $record->resolution_note,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockedWordRow(Model $record): array
    {
        /** @var BlockedWord $record */

        return [
            'id' => $record->id,
            'word' => $record->word,
            'match_type' => $record->match_type,
            'severity' => $record->severity,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'form' => Arr::only($record->toArray(), ['word', 'match_type', 'severity', 'is_active', 'notes']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function discussionForumRow(Model $record): array
    {
        /** @var DiscussionForum $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'visibility' => $this->enumValue($record->visibility),
            'status' => $this->enumValue($record->status),
            'threads_count' => $record->threads_count,
            'posts_count' => $record->posts_count,
            'form' => Arr::only($record->toArray(), [
                'course_id',
                'course_category_id',
                'created_by',
                'title',
                'slug',
                'description',
                'sort_order',
            ]) + [
                'visibility' => $this->enumValue($record->visibility),
                'status' => $this->enumValue($record->status),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function discussionThreadRow(Model $record): array
    {
        /** @var DiscussionThread $record */

        return [
            'id' => $record->id,
            'title' => $record->title,
            'forum' => $record->forum?->title,
            'author' => $record->user?->email,
            'status' => $this->enumValue($record->status),
            'replies_count' => $record->replies_count,
            'upvotes_count' => $record->upvotes_count,
            'form' => Arr::only($record->toArray(), [
                'discussion_forum_id',
                'user_id',
                'title',
                'slug',
                'body',
                'is_pinned',
                'is_locked',
            ]) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communityGroupRow(Model $record): array
    {
        /** @var CommunityGroup $record */

        return [
            'id' => $record->id,
            'name' => $record->name,
            'course' => $record->course?->title,
            'visibility' => $this->enumValue($record->visibility),
            'status' => $this->enumValue($record->status),
            'requires_paid_access' => $record->requires_paid_access ? 'Yes' : 'No',
            'members_count' => $record->members_count,
            'form' => Arr::only($record->toArray(), [
                'course_id',
                'created_by',
                'name',
                'slug',
                'description',
                'requires_paid_access',
            ]) + [
                'visibility' => $this->enumValue($record->visibility),
                'status' => $this->enumValue($record->status),
            ],
        ];
    }

    private function morphLabel(?string $type, ?int $id): string
    {
        if (! $type || ! $id) {
            return 'Unlinked';
        }

        return class_basename($type).' #'.$id;
    }

    private function findRecord(string $resource, int $id): Model
    {
        return $this->queryFactory->make($resource)->whereKey($id)->firstOrFail();
    }

    private function persist(Request $request, string $resource, ?Model $record = null): Model
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->persist($request, $resource, $record);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->persist($request, $resource, $record);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->persist($request, $resource, $record);
        }

        return match ($resource) {
            'users' => $this->persistUser($request, $record),
            'roles' => $this->persistRole($request, $record),
            'permissions' => $this->persistPermission($request, $record),
            'bloggers' => $this->persistBlogger($request, $record),
            'courses' => $this->persistCourse($request, $record),
            'lessons' => $this->persistLesson($request, $record),
            'blogs' => $this->persistBlog($request, $record),
            'cms' => $this->persistPage($request, $record),
            'home_hero' => $this->persistHomeHeroSetting($request, $record),
            'home_hero_slides' => $this->persistHomeHeroSlide($request, $record),
            'home_page_sections' => $this->persistHomePageSection($request, $record),
            'menus' => $this->persistMenu($request, $record),
            'media' => $this->persistMedia($request, $record),
            'ad_zones' => $this->persistAdZone($request, $record),
            'ad_campaigns' => $this->persistAdCampaign($request, $record),
            'ad_creatives' => $this->persistAdCreative($request, $record),
            'advertiser_requests' => $this->persistAdvertiserRequest($request, $record),
            'ad_pricing' => $this->persistAdPricing($request, $record),
            'instructors' => $this->persistInstructor($request, $record),
            'author_badges' => $this->persistAuthorBadge($request, $record),
            'editorial_revisions' => $this->persistEditorialRevision($request, $record),
            'reviewer_comments' => $this->persistReviewerComment($request, $record),
            'scheduled_publications' => $this->persistScheduledPublication($request, $record),
            'revenue_share_rules' => $this->persistRevenueShareRule($request, $record),
            'payment_products' => $this->persistPaymentProduct($request, $record),
            'payment_prices' => $this->persistPaymentPrice($request, $record),
            'payment_discounts' => $this->persistPaymentDiscount($request, $record),
            'payment_checkouts' => $this->persistPaymentCheckout($request, $record),
            'payment_orders' => $this->persistPaymentOrder($request, $record),
            'payment_subscriptions' => $this->persistPaymentSubscription($request, $record),
            'team_accounts' => $this->persistTeamAccount($request, $record),
            'team_seats' => $this->persistTeamSeat($request, $record),
            'payment_reconciliation' => $this->persistPaymentReconciliation($request, $record),
            'gift_purchases' => $this->persistGiftPurchase($request, $record),
            'affiliate_partners' => $this->persistAffiliatePartner($request, $record),
            'affiliate_visits' => $this->persistAffiliateVisit($request, $record),
            'referral_conversions' => $this->persistReferralConversion($request, $record),
            'checkout_recoveries' => $this->persistCheckoutRecovery($request, $record),
            'lead_magnets' => $this->persistLeadMagnet($request, $record),
            'newsletter_campaigns' => $this->persistNewsletterCampaign($request, $record),
            'lead_submissions' => $this->persistLeadSubmission($request, $record),
            'ab_experiments' => $this->persistAbExperiment($request, $record),
            'ab_variants' => $this->persistAbVariant($request, $record),
            'social_share_images' => $this->persistSocialShareImage($request, $record),
            'moderation_queue' => $this->persistModerationQueue($request, $record),
            'content_reports' => $this->persistContentReport($request, $record),
            'blocked_words' => $this->persistBlockedWord($request, $record),
            'discussion_forums' => $this->persistDiscussionForum($request, $record),
            'discussion_threads' => $this->persistDiscussionThread($request, $record),
            'community_groups' => $this->persistCommunityGroup($request, $record),
            'settings' => $this->persistSetting($request, $record),
            default => abort(404),
        };
    }

    private function persistUser(Request $request, ?Model $record): User
    {
        abort_unless($request->user()?->can('admin.roles.assign'), 403);

        $user = $record instanceof User ? $record : new User;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', Rule::in($this->enumValues(UserStatus::cases()))],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $user->fill(Arr::only($data, ['name', 'email', 'status']));

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make((string) $data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles'] ?? []);

        return $user;
    }

    private function persistRole(Request $request, ?Model $record): Role
    {
        abort_unless($request->user()?->can('admin.roles.assign'), 403);

        $role = $record instanceof Role ? $record : new Role(['guard_name' => 'web']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        if ($role->exists && $role->name === RoleName::SuperAdmin->value && $data['name'] !== RoleName::SuperAdmin->value) {
            abort(422, 'The super admin role cannot be renamed.');
        }

        $role->name = (string) $data['name'];
        $role->guard_name = 'web';
        $role->save();
        $role->syncPermissions($data['permissions'] ?? []);

        return $role;
    }

    private function persistPermission(Request $request, ?Model $record): Permission
    {
        $permission = $record instanceof Permission ? $record : new Permission(['guard_name' => 'web']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->name = (string) $data['name'];
        $permission->guard_name = 'web';
        $permission->save();

        return $permission;
    }

    private function persistBlogger(Request $request, ?Model $record): BloggerProfile
    {
        $profile = $record instanceof BloggerProfile ? $record : new BloggerProfile;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'phone' => ['nullable', 'string', 'max:255'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'application_reason' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->enumValues(BloggerStatus::cases()))],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $profile->fill($data);

        if ($profile->isDirty('status')) {
            $profile->reviewed_by = $request->user()?->id;
            $profile->reviewed_at = Carbon::now();
        }

        $profile->is_verified_creator = $this->enumValue($profile->getAttribute('status')) === BloggerStatus::Approved->value;

        $profile->save();
        $profile->user?->assignRole(RoleName::Blogger->value);

        return $profile;
    }

    private function persistCourse(Request $request, ?Model $record): Course
    {
        $course = $record instanceof Course ? $record : new Course(['created_by' => $request->user()?->id]);
        $data = $this->validatedContent($request, [
            'course_category_id' => ['nullable', Rule::exists('course_categories', 'id')->whereNull('deleted_at')],
            'course_subcategory_id' => [
                'nullable',
                Rule::exists('course_subcategories', 'id')->where(
                    fn ($query) => $query
                        ->where('course_category_id', $request->input('course_category_id'))
                        ->whereNull('deleted_at'),
                ),
            ],
            'thumbnail_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'ownership_video_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'ownership_video_url' => ['nullable', 'string', 'max:2048'],
            'ownership_statement' => ['nullable', 'string', 'max:2000'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'level' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:16'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_free' => ['boolean'],
        ]);

        $course->fill($data);
        $this->guardCoursePublication($course);
        $this->syncPublishedAt($course);
        $course->save();

        return $course;
    }

    private function persistLesson(Request $request, ?Model $record): CourseLesson
    {
        $lesson = $record instanceof CourseLesson ? $record : new CourseLesson;
        $data = $this->validatedContent($request, [
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'course_section_id' => ['nullable', Rule::exists('course_sections', 'id')],
            'order_number' => ['nullable', 'integer', 'min:0'],
            'content' => ['nullable', 'string'],
            'video_type' => ['required', Rule::in($this->enumValues(VideoType::cases()))],
            'video_url' => ['nullable', 'string', 'max:255'],
            'video_file_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'is_free' => ['boolean'],
            'is_paid' => ['boolean'],
            'preview_word_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $lesson->fill($data);
        $this->syncPublishedAt($lesson);
        $lesson->save();

        return $lesson;
    }

    private function persistBlog(Request $request, ?Model $record): BlogPost
    {
        $post = $record instanceof BlogPost ? $record : new BlogPost(['author_id' => $request->user()?->id]);
        $data = $this->validatedContent($request, [
            'blog_category_id' => ['nullable', Rule::exists('blog_categories', 'id')],
            'author_id' => ['nullable', Rule::exists('users', 'id')],
            'featured_image_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'is_featured' => ['boolean'],
            'blog_tag_ids' => ['array', 'max:50'],
            'blog_tag_ids.*' => ['integer', 'distinct', Rule::exists('blog_tags', 'id')],
        ]);

        $tagIds = $data['blog_tag_ids'] ?? [];
        unset($data['blog_tag_ids']);

        $fromStatus = $this->statusValue($post->getAttribute('status'));
        $toStatus = $data['status'] ?? null;

        if ($toStatus === PublishStatus::Published->value && ! in_array($fromStatus, [PublishStatus::Approved->value, PublishStatus::Published->value], true)) {
            abort(422, 'Approve this blog before publishing it.');
        }

        $data['content'] = $this->contentSanitizer->richText($data['content'] ?? '');
        $post->fill($data);
        $this->syncPublishedAt($post);
        $post->save();
        $post->tags()->sync($tagIds);

        return $post;
    }

    private function persistPage(Request $request, ?Model $record): Page
    {
        $page = $record instanceof Page ? $record : new Page(['author_id' => $request->user()?->id]);
        $data = $this->validatedContent($request, [
            'author_id' => ['nullable', Rule::exists('users', 'id')],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'template' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'seo_image' => ['nullable', 'string', 'max:255'],
        ]);

        $data['content'] = $this->contentSanitizer->richText($data['content'] ?? '');
        $page->fill($data);
        $this->syncPublishedAt($page);
        $page->save();

        return $page;
    }

    private function persistHomeHeroSetting(Request $request, ?Model $record): SiteSetting
    {
        $setting = $record instanceof SiteSetting
            ? $record
            : SiteSetting::query()->firstOrNew(['key' => 'home.hero']);

        $data = $request->validate([
            'mode' => ['required', Rule::in($this->enumValues(HomeHeroMode::cases()))],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'highlight_terms' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'search_enabled' => ['boolean'],
            'stats_enabled' => ['boolean'],
            'featured_course_enabled' => ['boolean'],
        ]);

        $setting->fill([
            'group' => 'home',
            'key' => 'home.hero',
            'value' => [
                'mode' => $data['mode'],
                'eyebrow' => $data['eyebrow'] ?: 'The leader in online learning',
                'heading' => $data['heading'] ?: 'Find the best courses from expert mentors.',
                'highlight_terms' => $this->homeHeroHighlightTerms($data['highlight_terms'] ?? 'courses, mentors'),
                'description' => $data['description'] ?? null,
                'search_enabled' => (bool) ($data['search_enabled'] ?? false),
                'stats_enabled' => (bool) ($data['stats_enabled'] ?? false),
                'featured_course_enabled' => (bool) ($data['featured_course_enabled'] ?? false),
            ],
            'is_encrypted' => false,
        ]);
        $setting->save();

        return $setting;
    }

    private function persistHomeHeroSlide(Request $request, ?Model $record): HomeHeroSlide
    {
        $slide = $record instanceof HomeHeroSlide ? $record : new HomeHeroSlide;
        $data = $request->validate([
            'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'target_url' => ['required', 'string', 'max:2048'],
            'text_position' => ['required', Rule::in(['left', 'center', 'right'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'opens_in_new_tab' => ['boolean'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['opens_in_new_tab'] = (bool) ($data['opens_in_new_tab'] ?? false);

        $slide->fill($data);
        $slide->save();

        return $slide;
    }

    private function persistHomePageSection(Request $request, ?Model $record): HomePageSection
    {
        $section = $record instanceof HomePageSection ? $record : new HomePageSection;
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', Rule::unique('home_page_sections', 'key')->ignore($section->id)],
            'type' => ['required', Rule::in($this->enumValues(HomePageSectionType::cases()))],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'background' => ['required', Rule::in(['white', 'soft', 'dark', 'accent'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'payload_content' => ['nullable', 'string'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '') ?? [];
        unset($data['payload_content']);

        $section->fill($data);
        $section->save();

        return $section;
    }

    private function persistMenu(Request $request, ?Model $record): Menu
    {
        $menu = $record instanceof Menu ? $record : new Menu;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $menu->fill($data);
        $menu->save();

        return $menu;
    }

    private function persistMedia(Request $request, ?Model $record): MediaAsset
    {
        $asset = $record instanceof MediaAsset ? $record : new MediaAsset(['uploaded_by' => $request->user()?->id]);
        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('media_folders', 'id')],
            'uploaded_by' => ['nullable', Rule::exists('users', 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'integer', 'min:0'],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', Rule::in($this->enumValues(MediaVisibility::cases()))],
        ]);

        $asset->fill($data);
        $asset->save();

        return $asset;
    }

    private function persistAdZone(Request $request, ?Model $record): AdZone
    {
        $zone = $record instanceof AdZone ? $record : new AdZone;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'max_creatives' => ['nullable', 'integer', 'min:1', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(AdZoneStatus::cases()))],
        ]);

        $zone->fill($data);
        $zone->save();

        return $zone;
    }

    private function persistAdCampaign(Request $request, ?Model $record): AdCampaign
    {
        $campaign = $record instanceof AdCampaign ? $record : new AdCampaign;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'advertiser_name' => ['required', 'string', 'max:255'],
            'advertiser_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(AdCampaignStatus::cases()))],
            'pricing_model' => ['required', Rule::in($this->enumValues(AdPricingModel::cases()))],
            'currency' => ['nullable', 'string', 'size:3'],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'daily_budget' => ['nullable', 'numeric', 'min:0'],
            'cpm_rate' => ['nullable', 'numeric', 'min:0'],
            'cpc_rate' => ['nullable', 'numeric', 'min:0'],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));

        $campaign->fill($data);
        $campaign->save();

        return $campaign;
    }

    private function persistAdCreative(Request $request, ?Model $record): AdCreative
    {
        $creative = $record instanceof AdCreative ? $record : new AdCreative;
        $data = $request->validate([
            'ad_campaign_id' => ['required', Rule::exists('ad_campaigns', 'id')],
            'ad_zone_id' => ['nullable', Rule::exists('ad_zones', 'id')],
            'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($this->enumValues(AdCreativeType::cases()))],
            'status' => ['required', Rule::in($this->enumValues(AdCreativeStatus::cases()))],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'cta_text' => ['nullable', 'string', 'max:255'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'html_snippet' => ['nullable', 'string'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $creative->fill($data);
        $creative->save();

        return $creative;
    }

    private function persistAdvertiserRequest(Request $request, ?Model $record): AdvertiserRequest
    {
        $advertiserRequest = $record instanceof AdvertiserRequest ? $record : new AdvertiserRequest;
        $data = $request->validate([
            'requested_ad_zone_id' => ['nullable', Rule::exists('ad_zones', 'id')],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'message' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->enumValues(AdvertiserRequestStatus::cases()))],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $advertiserRequest->fill($data);

        if ($advertiserRequest->isDirty('status') && $this->statusValue($advertiserRequest->getAttribute('status')) !== AdvertiserRequestStatus::Pending->value) {
            $actorId = $request->user()?->getKey();

            if (is_int($actorId) && $actorId >= 0) {
                $advertiserRequest->reviewed_by = $actorId;
            }

            $advertiserRequest->reviewed_at = now();
        }

        $advertiserRequest->save();

        return $advertiserRequest;
    }

    private function persistAdPricing(Request $request, ?Model $record): AdPricingSetting
    {
        $setting = $record instanceof AdPricingSetting ? $record : new AdPricingSetting;
        $data = $request->validate([
            'ad_zone_id' => ['nullable', Rule::exists('ad_zones', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'pricing_model' => ['required', Rule::in($this->enumValues(AdPricingModel::cases()))],
            'currency' => ['nullable', 'string', 'size:3'],
            'cpm_rate' => ['nullable', 'numeric', 'min:0'],
            'cpc_rate' => ['nullable', 'numeric', 'min:0'],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));

        $setting->fill($data);
        $setting->save();

        return $setting;
    }

    private function persistInstructor(Request $request, ?Model $record): InstructorProfile
    {
        $profile = $record instanceof InstructorProfile ? $record : new InstructorProfile;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id'), Rule::unique('instructor_profiles', 'user_id')->ignore($profile->id)],
            'avatar_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'display_name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'credentials' => ['nullable', 'string'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::in($this->enumValues(InstructorProfileStatus::cases()))],
            'is_verified_expert' => ['boolean'],
            'accepts_revenue_share' => ['boolean'],
            'payout_currency' => ['nullable', 'string', 'size:3'],
            'payout_account_reference' => ['nullable', 'string', 'max:255'],
            'admin_notes' => ['nullable', 'string'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['payout_currency'] = strtoupper((string) ($data['payout_currency'] ?? 'USD'));
        $data['is_verified_expert'] = (bool) ($data['is_verified_expert'] ?? false);
        $data['accepts_revenue_share'] = (bool) ($data['accepts_revenue_share'] ?? false);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $profile->fill($data);

        if ($profile->isDirty('status')) {
            $actorId = $request->user()?->getKey();

            if (is_int($actorId) && $actorId >= 0) {
                $profile->reviewed_by = $actorId;
            }

            $profile->reviewed_at = now();
        }

        $profile->save();

        return $profile;
    }

    private function persistAuthorBadge(Request $request, ?Model $record): AuthorBadge
    {
        $badge = $record instanceof AuthorBadge ? $record : new AuthorBadge;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'marks_verified_expert' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $data['color'] = $data['color'] ?: 'slate';
        $data['marks_verified_expert'] = (bool) ($data['marks_verified_expert'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $badge->fill($data);
        $badge->save();

        $sync = [];

        foreach ($userIds as $userId) {
            $sync[(int) $userId] = [
                'awarded_by' => $request->user()?->id,
                'awarded_at' => now(),
            ];
        }

        $badge->users()->sync($sync);

        return $badge;
    }

    private function persistEditorialRevision(Request $request, ?Model $record): EditorialRevision
    {
        $revision = $record instanceof EditorialRevision ? $record : new EditorialRevision;
        $data = $request->validate([
            'author_id' => ['nullable', Rule::exists('users', 'id')],
            'reviewer_id' => ['nullable', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'payload_content' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->enumValues(EditorialRevisionStatus::cases()))],
            'submitted_at' => ['nullable', 'date'],
            'reviewed_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '');
        unset($data['payload_content']);

        $revision->fill($data);
        $revision->save();

        return $revision;
    }

    private function persistReviewerComment(Request $request, ?Model $record): ReviewerComment
    {
        $comment = $record instanceof ReviewerComment ? $record : new ReviewerComment;
        $data = $request->validate([
            'editorial_revision_id' => ['required', Rule::exists('editorial_revisions', 'id')],
            'reviewer_id' => ['nullable', Rule::exists('users', 'id')],
            'field_path' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_resolved' => ['boolean'],
        ]);

        $wasResolved = (bool) $comment->is_resolved;
        $data['is_resolved'] = (bool) ($data['is_resolved'] ?? false);

        $comment->fill($data);

        if (! $wasResolved && $data['is_resolved']) {
            $actorId = $request->user()?->getKey();

            if (is_int($actorId) && $actorId >= 0) {
                $comment->resolved_by = $actorId;
            }

            $comment->resolved_at = now();
        }

        if ($wasResolved && ! $data['is_resolved']) {
            $comment->resolved_by = null;
            $comment->resolved_at = null;
        }

        $comment->save();

        return $comment;
    }

    private function persistScheduledPublication(Request $request, ?Model $record): ScheduledPublication
    {
        $publication = $record instanceof ScheduledPublication ? $record : new ScheduledPublication;
        $data = $request->validate([
            'editorial_revision_id' => ['nullable', Rule::exists('editorial_revisions', 'id')],
            'created_by' => ['nullable', Rule::exists('users', 'id')],
            'approved_by' => ['nullable', Rule::exists('users', 'id')],
            'publish_at' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in($this->enumValues(ScheduledPublicationStatus::cases()))],
            'metadata_content' => ['nullable', 'string'],
        ]);

        if (! $publication->exists && isset($data['editorial_revision_id'])) {
            $revision = EditorialRevision::query()
                ->whereKey($data['editorial_revision_id'])
                ->first();

            if ($revision instanceof EditorialRevision && $revision->getAttribute('editorialable_type') && $revision->getAttribute('editorialable_id')) {
                $publication->publishable_type = $revision->getAttribute('editorialable_type');
                $publication->publishable_id = $revision->getAttribute('editorialable_id');
            }
        }

        $data['timezone'] = $data['timezone'] ?: config('app.timezone', 'UTC');
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $publication->fill($data);
        $publication->save();

        return $publication;
    }

    private function persistRevenueShareRule(Request $request, ?Model $record): RevenueShareRule
    {
        $rule = $record instanceof RevenueShareRule ? $record : new RevenueShareRule;
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'instructor_profile_id' => ['nullable', Rule::exists('instructor_profiles', 'id')],
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'payment_product_id' => ['nullable', Rule::exists('payment_products', 'id')],
            'type' => ['required', Rule::in($this->enumValues(RevenueShareRuleType::cases()))],
            'status' => ['required', Rule::in($this->enumValues(RevenueShareRuleStatus::cases()))],
            'share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['share_percent'] = $data['share_percent'] ?? 0;
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $rule->fill($data);
        $rule->save();

        return $rule;
    }

    private function persistPaymentProduct(Request $request, ?Model $record): PaymentProduct
    {
        $product = $record instanceof PaymentProduct ? $record : new PaymentProduct;
        $data = $request->validate([
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'course_bundle_id' => ['nullable', Rule::exists('course_bundles', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in($this->enumValues(PaymentProductType::cases()))],
            'status' => ['required', Rule::in($this->enumValues(PaymentProductStatus::cases()))],
            'paddle_product_id' => ['nullable', 'string', 'max:255', Rule::unique('payment_products', 'paddle_product_id')->ignore($product->id)],
            'tax_category' => ['nullable', 'string', 'max:255'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['tax_category'] = $data['tax_category'] ?: 'training-services';
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $product->fill($data);
        $product->save();

        return $product;
    }

    private function persistPaymentPrice(Request $request, ?Model $record): PaymentPrice
    {
        $price = $record instanceof PaymentPrice ? $record : new PaymentPrice;
        $data = $request->validate([
            'payment_product_id' => ['required', Rule::exists('payment_products', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'paddle_price_id' => ['nullable', 'string', 'max:255', Rule::unique('payment_prices', 'paddle_price_id')->ignore($price->id)],
            'billing_interval' => ['required', Rule::in($this->enumValues(PaymentBillingInterval::cases()))],
            'is_recurring' => ['boolean'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount' => ['required', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'seat_min' => ['nullable', 'integer', 'min:1'],
            'seat_max' => ['nullable', 'integer', 'min:1', 'gte:seat_min'],
            'is_active' => ['boolean'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $price->fill($data);
        $price->save();

        return $price;
    }

    private function persistPaymentCheckout(Request $request, ?Model $record): PaymentCheckout
    {
        $checkout = $record instanceof PaymentCheckout ? $record : new PaymentCheckout;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'payment_price_id' => ['required', Rule::exists('payment_prices', 'id')],
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'team_account_id' => ['nullable', Rule::exists('team_accounts', 'id')],
            'payment_discount_id' => ['nullable', Rule::exists('payment_discounts', 'id')],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in($this->enumValues(PaymentCheckoutStatus::cases()))],
            'paddle_transaction_id' => ['nullable', 'string', 'max:255', Rule::unique('payment_checkouts', 'paddle_transaction_id')->ignore($checkout->id)],
            'checkout_url' => ['nullable', 'url', 'max:2048'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
            'custom_data_content' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $data['quantity'] = (int) ($data['quantity'] ?? 1);
        $data['discount_amount'] = (int) ($data['discount_amount'] ?? 0);
        $data['custom_data'] = $this->structuredValue($data['custom_data_content'] ?? '');
        unset($data['custom_data_content']);

        $checkout->fill($data);
        $checkout->save();

        return $checkout;
    }

    private function persistPaymentOrder(Request $request, ?Model $record): PaymentOrder
    {
        $order = $record instanceof PaymentOrder ? $record : new PaymentOrder;
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'team_account_id' => ['nullable', Rule::exists('team_accounts', 'id')],
            'payment_checkout_id' => ['nullable', Rule::exists('payment_checkouts', 'id')],
            'provider' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in($this->enumValues(PaymentOrderStatus::cases()))],
            'paddle_transaction_id' => ['nullable', 'string', 'max:255', Rule::unique('payment_orders', 'paddle_transaction_id')->ignore($order->id)],
            'paddle_customer_id' => ['nullable', 'string', 'max:255'],
            'paddle_subscription_id' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotal' => ['nullable', 'integer', 'min:0'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
            'payload_content' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '');
        unset($data['payload_content']);

        $order->fill($data);
        $order->save();

        return $order;
    }

    private function persistPaymentSubscription(Request $request, ?Model $record): PaymentSubscription
    {
        $subscription = $record instanceof PaymentSubscription ? $record : new PaymentSubscription;
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'team_account_id' => ['nullable', Rule::exists('team_accounts', 'id')],
            'payment_product_id' => ['nullable', Rule::exists('payment_products', 'id')],
            'payment_price_id' => ['nullable', Rule::exists('payment_prices', 'id')],
            'provider' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in($this->enumValues(PaymentSubscriptionStatus::cases()))],
            'paddle_subscription_id' => ['required', 'string', 'max:255', Rule::unique('payment_subscriptions', 'paddle_subscription_id')->ignore($subscription->id)],
            'paddle_customer_id' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'current_period_starts_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'canceled_at' => ['nullable', 'date'],
            'next_billed_at' => ['nullable', 'date'],
            'payload_content' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['quantity'] = (int) ($data['quantity'] ?? 1);
        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '');
        unset($data['payload_content']);

        $subscription->fill($data);
        $subscription->save();

        return $subscription;
    }

    private function persistTeamAccount(Request $request, ?Model $record): TeamAccount
    {
        $team = $record instanceof TeamAccount ? $record : new TeamAccount;
        $data = $request->validate([
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(TeamAccountStatus::cases()))],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'paddle_customer_id' => ['nullable', 'string', 'max:255'],
            'paddle_subscription_id' => ['nullable', 'string', 'max:255'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['seat_limit'] = (int) ($data['seat_limit'] ?? 1);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $team->fill($data);
        $team->save();

        return $team;
    }

    private function persistTeamSeat(Request $request, ?Model $record): TeamSeat
    {
        $seat = $record instanceof TeamSeat ? $record : new TeamSeat;
        $teamAccountId = $request->integer('team_account_id');
        $data = $request->validate([
            'team_account_id' => ['required', Rule::exists('team_accounts', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('team_seats', 'email')
                    ->where(fn ($query) => $query->where('team_account_id', $teamAccountId))
                    ->ignore($seat->id),
            ],
            'role' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in($this->enumValues(TeamSeatStatus::cases()))],
            'invited_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
        ]);

        $data['role'] = $data['role'] ?: 'member';

        $seat->fill($data);
        $seat->save();

        return $seat;
    }

    private function persistPaymentReconciliation(Request $request, ?Model $record): PaymentReconciliationRecord
    {
        $reconciliation = $record instanceof PaymentReconciliationRecord ? $record : new PaymentReconciliationRecord;
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'event_id' => ['nullable', 'string', 'max:255'],
            'record_type' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:64'],
            'paddle_transaction_id' => ['nullable', 'string', 'max:255'],
            'paddle_subscription_id' => ['nullable', 'string', 'max:255'],
            'paddle_customer_id' => ['nullable', 'string', 'max:255'],
            'payload_content' => ['nullable', 'string'],
            'reconciled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '');
        unset($data['payload_content']);

        $reconciliation->fill($data);
        $reconciliation->save();

        return $reconciliation;
    }

    private function persistPaymentDiscount(Request $request, ?Model $record): PaymentDiscount
    {
        $discount = $record instanceof PaymentDiscount ? $record : new PaymentDiscount;
        $data = $request->validate([
            'payment_product_id' => ['nullable', Rule::exists('payment_products', 'id')],
            'payment_price_id' => ['nullable', Rule::exists('payment_prices', 'id')],
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('payment_discounts', 'code')->ignore($discount->id)],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in($this->enumValues(DiscountType::cases()))],
            'value' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['required', Rule::in($this->enumValues(GrowthStatus::cases()))],
            'is_launch_offer' => ['boolean'],
            'paddle_discount_id' => ['nullable', 'string', 'max:255', Rule::unique('payment_discounts', 'paddle_discount_id')->ignore($discount->id)],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['code'] = strtoupper((string) $data['code']);
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['is_launch_offer'] = (bool) ($data['is_launch_offer'] ?? false);
        $data['per_user_limit'] = (int) ($data['per_user_limit'] ?? 1);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $discount->fill($data);
        $discount->save();

        return $discount;
    }

    private function persistGiftPurchase(Request $request, ?Model $record): GiftPurchase
    {
        $gift = $record instanceof GiftPurchase ? $record : new GiftPurchase;
        $data = $request->validate([
            'purchaser_id' => ['required', Rule::exists('users', 'id')],
            'recipient_user_id' => ['nullable', Rule::exists('users', 'id')],
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'course_bundle_id' => ['nullable', Rule::exists('course_bundles', 'id')],
            'payment_product_id' => ['nullable', Rule::exists('payment_products', 'id')],
            'payment_price_id' => ['nullable', Rule::exists('payment_prices', 'id')],
            'payment_checkout_id' => ['nullable', Rule::exists('payment_checkouts', 'id')],
            'recipient_email' => ['required', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('gift_purchases', 'code')->ignore($gift->id)],
            'status' => ['required', Rule::in($this->enumValues(GiftPurchaseStatus::cases()))],
            'message' => ['nullable', 'string'],
            'purchased_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'redeemed_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['recipient_email'] = strtolower((string) $data['recipient_email']);
        $data['code'] = strtoupper((string) $data['code']);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $gift->fill($data);
        $gift->save();

        return $gift;
    }

    private function persistAffiliatePartner(Request $request, ?Model $record): AffiliatePartner
    {
        $partner = $record instanceof AffiliatePartner ? $record : new AffiliatePartner;
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('affiliate_partners', 'code')->ignore($partner->id)],
            'status' => ['required', Rule::in($this->enumValues(AffiliateStatus::cases()))],
            'commission_rate_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'cookie_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'payout_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['code'] = strtoupper((string) $data['code']);
        $data['commission_rate_basis_points'] = (int) ($data['commission_rate_basis_points'] ?? 1000);
        $data['cookie_days'] = (int) ($data['cookie_days'] ?? 30);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $partner->fill($data);
        $partner->save();

        return $partner;
    }

    private function persistAffiliateVisit(Request $request, ?Model $record): AffiliateVisit
    {
        $visit = $record instanceof AffiliateVisit ? $record : new AffiliateVisit;
        $data = $request->validate([
            'affiliate_partner_id' => ['required', Rule::exists('affiliate_partners', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'visitor_id' => ['required', 'string', 'max:64'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'referrer_url' => ['nullable', 'string', 'max:2048'],
            'clicked_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $data['clicked_at'] = $data['clicked_at'] ?? now();
        $data['expires_at'] = $data['expires_at'] ?? now()->addDays(30);

        $visit->fill($data);
        $visit->save();

        return $visit;
    }

    private function persistReferralConversion(Request $request, ?Model $record): ReferralConversion
    {
        $conversion = $record instanceof ReferralConversion ? $record : new ReferralConversion;
        $data = $request->validate([
            'affiliate_partner_id' => ['required', Rule::exists('affiliate_partners', 'id')],
            'affiliate_visit_id' => ['nullable', Rule::exists('affiliate_visits', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'payment_checkout_id' => ['nullable', Rule::exists('payment_checkouts', 'id')],
            'payment_order_id' => ['nullable', Rule::exists('payment_orders', 'id')],
            'status' => ['required', Rule::in($this->enumValues(ReferralConversionStatus::cases()))],
            'amount' => ['nullable', 'integer', 'min:0'],
            'commission_amount' => ['nullable', 'integer', 'min:0'],
            'converted_at' => ['nullable', 'date'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['amount'] = (int) ($data['amount'] ?? 0);
        $data['commission_amount'] = (int) ($data['commission_amount'] ?? 0);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $conversion->fill($data);
        $conversion->save();

        return $conversion;
    }

    private function persistCheckoutRecovery(Request $request, ?Model $record): AbandonedCheckoutRecovery
    {
        $recovery = $record instanceof AbandonedCheckoutRecovery ? $record : new AbandonedCheckoutRecovery;
        $data = $request->validate([
            'payment_checkout_id' => ['required', Rule::exists('payment_checkouts', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(CheckoutRecoveryStatus::cases()))],
            'recovery_token' => ['required', 'string', 'max:255', Rule::unique('abandoned_checkout_recoveries', 'recovery_token')->ignore($recovery->id)],
            'recovery_url' => ['nullable', 'string', 'max:2048'],
            'reminder_count' => ['nullable', 'integer', 'min:0'],
            'last_reminded_at' => ['nullable', 'date'],
            'recovered_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['reminder_count'] = (int) ($data['reminder_count'] ?? 0);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $recovery->fill($data);
        $recovery->save();

        return $recovery;
    }

    private function persistLeadMagnet(Request $request, ?Model $record): LeadMagnet
    {
        $leadMagnet = $record instanceof LeadMagnet ? $record : new LeadMagnet;
        $data = $request->validate([
            'asset_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('lead_magnets', 'slug')->ignore($leadMagnet->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->enumValues(GrowthStatus::cases()))],
            'form_headline' => ['nullable', 'string', 'max:255'],
            'delivery_url' => ['nullable', 'string', 'max:2048'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $leadMagnet->fill($data);
        $leadMagnet->save();

        return $leadMagnet;
    }

    private function persistNewsletterCampaign(Request $request, ?Model $record): NewsletterCampaign
    {
        $campaign = $record instanceof NewsletterCampaign ? $record : new NewsletterCampaign;
        $data = $request->validate([
            'lead_magnet_id' => ['nullable', Rule::exists('lead_magnets', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('newsletter_campaigns', 'slug')->ignore($campaign->id)],
            'subject' => ['required', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(GrowthStatus::cases()))],
            'scheduled_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['audience'] = $data['audience'] ?: 'all_leads';
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $campaign->fill($data);
        $campaign->save();

        return $campaign;
    }

    private function persistLeadSubmission(Request $request, ?Model $record): LeadSubmission
    {
        $submission = $record instanceof LeadSubmission ? $record : new LeadSubmission;
        $leadMagnetId = $request->integer('lead_magnet_id');
        $data = $request->validate([
            'lead_magnet_id' => ['required', Rule::exists('lead_magnets', 'id')],
            'newsletter_campaign_id' => ['nullable', Rule::exists('newsletter_campaigns', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('lead_submissions', 'email')
                    ->where(fn ($query) => $query->where('lead_magnet_id', $leadMagnetId))
                    ->ignore($submission->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(LeadSubmissionStatus::cases()))],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['email'] = strtolower((string) $data['email']);
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $submission->fill($data);
        $submission->save();

        return $submission;
    }

    private function persistAbExperiment(Request $request, ?Model $record): AbExperiment
    {
        $experiment = $record instanceof AbExperiment ? $record : new AbExperiment;
        $data = $request->validate([
            'key' => ['required', 'string', 'max:128', Rule::unique('ab_experiments', 'key')->ignore($experiment->id)],
            'name' => ['required', 'string', 'max:255'],
            'surface' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(GrowthStatus::cases()))],
            'winning_variant_key' => ['nullable', 'string', 'max:128'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['surface'] = $data['surface'] ?: 'pricing_cta';
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $experiment->fill($data);
        $experiment->save();

        return $experiment;
    }

    private function persistAbVariant(Request $request, ?Model $record): AbVariant
    {
        $variant = $record instanceof AbVariant ? $record : new AbVariant;
        $experimentId = $request->integer('ab_experiment_id');
        $data = $request->validate([
            'ab_experiment_id' => ['required', Rule::exists('ab_experiments', 'id')],
            'key' => [
                'required',
                'string',
                'max:128',
                Rule::unique('ab_variants', 'key')
                    ->where(fn ($query) => $query->where('ab_experiment_id', $experimentId))
                    ->ignore($variant->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'payload_content' => ['nullable', 'string'],
        ]);

        $data['weight'] = (int) ($data['weight'] ?? 50);
        $data['payload'] = $this->structuredValue($data['payload_content'] ?? '');
        unset($data['payload_content']);

        $variant->fill($data);
        $variant->save();

        return $variant;
    }

    private function persistSocialShareImage(Request $request, ?Model $record): SocialShareImage
    {
        $image = $record instanceof SocialShareImage ? $record : new SocialShareImage;
        $data = $request->validate([
            'shareable_type' => ['required', 'string', 'max:255'],
            'shareable_id' => ['required', 'integer', 'min:1'],
            'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'title' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(GrowthStatus::cases()))],
            'metadata_content' => ['nullable', 'string'],
        ]);

        $data['template'] = $data['template'] ?: 'default';
        $data['metadata'] = $this->structuredValue($data['metadata_content'] ?? '');
        unset($data['metadata_content']);

        $image->fill($data);
        $image->save();

        return $image;
    }

    private function persistModerationQueue(Request $request, ?Model $record): ModerationQueueItem
    {
        $item = $record instanceof ModerationQueueItem ? $record : new ModerationQueueItem;
        $data = $request->validate([
            'status' => ['required', Rule::in($this->enumValues(CommunityContentStatus::cases()))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string'],
        ]);

        $item->fill($data);

        if ($data['status'] !== CommunityContentStatus::Pending->value && blank($item->reviewed_at)) {
            $item->reviewed_at = now();
        }

        $item->save();
        $this->applyModerationDecision($item, $data['status'], $request->user(), $data['resolution_note'] ?? null);

        return $item;
    }

    private function persistContentReport(Request $request, ?Model $record): ContentReport
    {
        $report = $record instanceof ContentReport ? $record : new ContentReport;
        $data = $request->validate([
            'status' => ['required', Rule::in($this->enumValues(CommunityReportStatus::cases()))],
            'reviewed_by' => ['nullable', Rule::exists('users', 'id')],
            'reason' => ['required', 'string', 'max:128'],
            'details' => ['nullable', 'string'],
            'resolution_note' => ['nullable', 'string'],
        ]);

        if ($data['status'] !== CommunityReportStatus::Open->value) {
            $data['reviewed_by'] = $data['reviewed_by'] ?? $request->user()?->id;
            $data['reviewed_at'] = now();
        }

        $report->fill($data);
        $report->save();

        return $report;
    }

    private function persistBlockedWord(Request $request, ?Model $record): BlockedWord
    {
        $blockedWord = $record instanceof BlockedWord ? $record : new BlockedWord;
        $data = $request->validate([
            'word' => ['required', 'string', 'max:255', Rule::unique('blocked_words', 'word')->ignore($blockedWord->id)],
            'match_type' => ['required', 'string', Rule::in(['contains', 'word', 'exact'])],
            'severity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['severity'] = (int) ($data['severity'] ?? 1);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $blockedWord->fill($data);
        $blockedWord->save();

        return $blockedWord;
    }

    private function persistDiscussionForum(Request $request, ?Model $record): DiscussionForum
    {
        $forum = $record instanceof DiscussionForum ? $record : new DiscussionForum;
        $data = $request->validate([
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'course_category_id' => ['nullable', Rule::exists('course_categories', 'id')],
            'created_by' => ['nullable', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in($this->enumValues(CommunityVisibility::cases()))],
            'status' => ['required', Rule::in($this->enumValues(CommunityContentStatus::cases()))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $forum->fill($data);
        $forum->save();

        return $forum;
    }

    private function persistDiscussionThread(Request $request, ?Model $record): DiscussionThread
    {
        $thread = $record instanceof DiscussionThread ? $record : new DiscussionThread;
        $data = $request->validate([
            'discussion_forum_id' => ['required', Rule::exists('discussion_forums', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['required', Rule::in($this->enumValues(CommunityContentStatus::cases()))],
            'is_pinned' => ['boolean'],
            'is_locked' => ['boolean'],
        ]);

        $data['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
        $data['is_locked'] = (bool) ($data['is_locked'] ?? false);
        $data['last_activity_at'] = $thread->last_activity_at ?? now();

        $thread->fill($data);
        $thread->save();

        return $thread;
    }

    private function persistCommunityGroup(Request $request, ?Model $record): CommunityGroup
    {
        $group = $record instanceof CommunityGroup ? $record : new CommunityGroup;
        $data = $request->validate([
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'created_by' => ['nullable', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in($this->enumValues(CommunityVisibility::cases()))],
            'status' => ['required', Rule::in($this->enumValues(CommunityContentStatus::cases()))],
            'requires_paid_access' => ['boolean'],
        ]);

        $data['requires_paid_access'] = (bool) ($data['requires_paid_access'] ?? true);

        $group->fill($data);
        $group->save();

        return $group;
    }

    private function persistSetting(Request $request, ?Model $record): SiteSetting
    {
        $setting = $record instanceof SiteSetting ? $record : new SiteSetting;
        $data = $request->validate([
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', Rule::unique('site_settings', 'key')->ignore($setting->id)],
            'value_content' => ['nullable', 'string'],
            'is_encrypted' => ['boolean'],
        ]);

        $setting->fill([
            'group' => $data['group'],
            'key' => $data['key'],
            'value' => $this->settingValue($data['value_content'] ?? ''),
            'is_encrypted' => (bool) ($data['is_encrypted'] ?? false),
        ]);
        $setting->save();

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validatedContent(Request $request, array $extra): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in($this->enumValues(PublishStatus::cases()))],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
            ...$extra,
        ]);
    }

    private function syncPublishedAt(Model $record): void
    {
        $status = $record->getAttribute('status');

        if ($status instanceof PublishStatus) {
            $status = $status->value;
        }

        if ($status === PublishStatus::Published->value && blank($record->getAttribute('published_at'))) {
            $record->setAttribute('published_at', now());
        }

        if ($status !== PublishStatus::Published->value) {
            $record->setAttribute('published_at', null);
        }
    }

    private function applyBulkMutation(string $resource, Model $record, string $action, ?string $value, ?string $note, Request $request): void
    {
        if ($this->courseCatalog->applyBulkMutation($resource, $record, $action, $value)) {
            return;
        }

        if ($this->editorialCms->applyBulkMutation($resource, $record, $action, $value)) {
            return;
        }

        if ($this->learningAdministration->applyBulkMutation($resource, $record, $action, $value)) {
            return;
        }

        if ($resource === 'media' && $action === 'visibility') {
            $record->setAttribute('visibility', $value);
            $record->save();

            return;
        }

        if (in_array($resource, ['ad_pricing', 'payment_prices', 'author_badges', 'blocked_words', 'home_page_sections'], true) && $action === 'active') {
            $record->setAttribute('is_active', $value === 'active');
            $record->save();

            return;
        }

        if ($resource === 'content_reports' && $record instanceof ContentReport && $action === 'status') {
            $record->setAttribute('status', $value);
            $record->setAttribute('reviewed_by', $request->user()?->id);
            $record->setAttribute('reviewed_at', now());
            $record->setAttribute('resolution_note', $note);
            $record->save();

            return;
        }

        if ($resource === 'reviewer_comments' && $action === 'resolved') {
            $record->setAttribute('is_resolved', $value === 'resolved');
            $record->setAttribute('resolved_by', $value === 'resolved' ? $request->user()?->id : null);
            $record->setAttribute('resolved_at', $value === 'resolved' ? now() : null);
            $record->save();

            return;
        }

        if ($action !== 'status') {
            abort(422, 'Unsupported bulk action.');
        }

        $fromStatus = $record->getAttribute('status');

        if ($resource === 'blogs' && $value === PublishStatus::Published->value && ! in_array($this->statusValue($fromStatus), [PublishStatus::Approved->value, PublishStatus::Published->value], true)) {
            abort(422, 'Approve this blog before publishing it.');
        }

        $record->setAttribute('status', $value);

        if ($resource === 'courses' && $record instanceof Course) {
            $this->guardCoursePublication($record);
        }

        if ($resource === 'moderation_queue' && $record instanceof ModerationQueueItem) {
            $record->setAttribute('assigned_to', $request->user()?->id);
            $record->setAttribute('reviewed_at', now());
            $record->setAttribute('resolution_note', $note);
            $record->save();
            $this->applyModerationDecision($record, (string) $value, $request->user(), $note);

            return;
        }

        if ($resource === 'bloggers' && $record instanceof BloggerProfile) {
            $record->reviewed_by = $request->user()?->id;
            $record->reviewed_at = Carbon::now();
            $record->admin_notes = $note ?: $record->admin_notes;
        }

        if ($resource === 'advertiser_requests' && $record instanceof AdvertiserRequest && $value !== AdvertiserRequestStatus::Pending->value) {
            $actorId = $request->user()?->getKey();

            if (is_int($actorId) && $actorId >= 0) {
                $record->reviewed_by = $actorId;
            }

            $record->reviewed_at = Carbon::now();
            $record->admin_notes = $note ?: $record->admin_notes;
        }

        if ($resource === 'instructors' && $record instanceof InstructorProfile) {
            $actorId = $request->user()?->getKey();

            if (is_int($actorId) && $actorId >= 0) {
                $record->reviewed_by = $actorId;
            }

            $record->reviewed_at = Carbon::now();
            $record->admin_notes = $note ?: $record->admin_notes;
        }

        if ($resource === 'editorial_revisions' && $record instanceof EditorialRevision) {
            if ($value === EditorialRevisionStatus::Submitted->value && blank($record->submitted_at)) {
                $record->submitted_at = now();
            }

            if (in_array($value, [EditorialRevisionStatus::Approved->value, EditorialRevisionStatus::ChangesRequested->value], true)) {
                $actorId = $request->user()?->getKey();

                if (is_int($actorId) && $actorId >= 0) {
                    $record->reviewer_id = $actorId;
                }

                $record->reviewed_at = now();
            }

            if ($value === EditorialRevisionStatus::Published->value) {
                $record->published_at = now();
            }
        }

        if ($resource === 'scheduled_publications' && $record instanceof ScheduledPublication && $value === ScheduledPublicationStatus::Published->value) {
            $record->published_at = now();
        }

        if (in_array($resource, ['courses', 'lessons', 'blogs', 'cms'], true)) {
            if ($value === PublishStatus::Published->value) {
                $record->setAttribute('published_at', now());
            } else {
                $record->setAttribute('published_at', null);
            }

            if ($value === PublishStatus::Rejected->value) {
                $record->setAttribute('rejection_reason', $note);
            } elseif (filled($note)) {
                $record->setAttribute('admin_notes', $note);
            }
        }

        $record->save();
    }

    private function applyModerationDecision(ModerationQueueItem $item, string $status, ?User $moderator, ?string $note): void
    {
        $subject = $item->subject;

        if (! $subject) {
            return;
        }

        if ($status === CommunityContentStatus::Approved->value) {
            $this->moderationService->approve($subject, $moderator, $note);

            return;
        }

        if (in_array($status, [
            CommunityContentStatus::Rejected->value,
            CommunityContentStatus::Spam->value,
            CommunityContentStatus::Hidden->value,
        ], true)) {
            $this->moderationService->reject($subject, $moderator, $note);

            $item->forceFill([
                'status' => $status,
                'assigned_to' => $moderator?->id,
                'reviewed_at' => now(),
                'resolution_note' => $note,
            ])->save();
        }
    }

    private function recordApprovalIfNeeded(Request $request, string $resource, Model $record, mixed $fromStatus, mixed $toStatus, ?string $note): void
    {
        if (! in_array($resource, ['bloggers', 'instructors', 'courses', 'lessons', 'blogs', 'cms'], true)) {
            return;
        }

        if ($this->statusValue($fromStatus) === $this->statusValue($toStatus)) {
            return;
        }

        $this->approvalRecorder->record(
            $request,
            $record,
            $this->statusValue($toStatus) ?? 'updated',
            $fromStatus,
            $toStatus,
            $note,
            ['resource' => $resource],
        );
    }

    private function decisionNote(Request $request): ?string
    {
        return $request->string('rejection_reason')->toString()
            ?: $request->string('admin_notes')->toString()
            ?: null;
    }

    private function guardDeletion(Request $request, string $resource, Model $record): void
    {
        if ($this->courseCatalog->supports($resource)) {
            $this->courseCatalog->guardDeletion($resource, $record);
        }

        if ($this->editorialCms->supports($resource)) {
            $this->editorialCms->guardDeletion($resource, $record);
        }

        if ($this->learningAdministration->supports($resource)) {
            $this->learningAdministration->guardDeletion($resource, $record);
        }

        if ($record instanceof MediaAsset && $record->usages()->exists()) {
            abort(422, 'This media asset is still referenced by published or managed content.');
        }

        if ($resource === 'users' && $record instanceof User && $request->user()?->is($record)) {
            abort(422, 'You cannot delete your own account.');
        }

        if ($resource === 'roles' && $record instanceof Role && $record->name === RoleName::SuperAdmin->value) {
            abort(422, 'The super admin role cannot be deleted.');
        }
    }

    private function markContentAsTrashed(Model $record): void
    {
        if ($record instanceof Course || $record instanceof BlogPost) {
            $record->forceFill([
                'status' => PublishStatus::Trashed,
                'published_at' => null,
            ])->save();
        }
    }

    /**
     * @return array<string, int>
     */
    private function metricsFor(string $resource): array
    {
        if ($this->courseCatalog->supports($resource)) {
            return $this->courseCatalog->metrics($resource);
        }

        if ($this->editorialCms->supports($resource)) {
            return $this->editorialCms->metrics($resource);
        }

        if ($this->learningAdministration->supports($resource)) {
            return $this->learningAdministration->metrics($resource);
        }

        if ($this->commerceOperations->supports($resource)) {
            return $this->commerceOperations->metrics($resource);
        }

        if ($this->communityOperations->supports($resource)) {
            return $this->communityOperations->metrics($resource);
        }

        if ($this->operationsCenter->supports($resource)) {
            return $this->operationsCenter->metrics($resource);
        }

        return match ($resource) {
            'users' => ['total' => User::count(), 'active' => User::query()->where('status', UserStatus::Active->value)->count()],
            'bloggers' => ['pending' => BloggerProfile::query()->where('status', BloggerStatus::Pending->value)->count(), 'approved' => BloggerProfile::query()->where('status', BloggerStatus::Approved->value)->count()],
            'instructors' => ['pending' => InstructorProfile::query()->where('status', InstructorProfileStatus::Pending->value)->count(), 'approved' => InstructorProfile::query()->where('status', InstructorProfileStatus::Approved->value)->count()],
            'author_badges' => ['active' => AuthorBadge::query()->where('is_active', true)->count(), 'verified' => AuthorBadge::query()->where('marks_verified_expert', true)->count()],
            'editorial_revisions' => ['submitted' => EditorialRevision::query()->where('status', EditorialRevisionStatus::Submitted->value)->count(), 'approved' => EditorialRevision::query()->where('status', EditorialRevisionStatus::Approved->value)->count()],
            'reviewer_comments' => ['open' => ReviewerComment::query()->where('is_resolved', false)->count(), 'resolved' => ReviewerComment::query()->where('is_resolved', true)->count()],
            'scheduled_publications' => ['scheduled' => ScheduledPublication::query()->where('status', ScheduledPublicationStatus::Scheduled->value)->count(), 'published' => ScheduledPublication::query()->where('status', ScheduledPublicationStatus::Published->value)->count()],
            'revenue_share_rules' => ['active' => RevenueShareRule::query()->where('status', RevenueShareRuleStatus::Active->value)->count(), 'draft' => RevenueShareRule::query()->where('status', RevenueShareRuleStatus::Draft->value)->count()],
            'courses' => ['published' => Course::query()->where('status', PublishStatus::Published->value)->count(), 'draft' => Course::query()->where('status', PublishStatus::Draft->value)->count()],
            'lessons' => ['published' => CourseLesson::query()->where('status', PublishStatus::Published->value)->count(), 'draft' => CourseLesson::query()->where('status', PublishStatus::Draft->value)->count()],
            'blogs' => [
                'published' => BlogPost::query()->where('status', PublishStatus::Published->value)->count(),
                'submitted' => BlogPost::query()->where('status', PublishStatus::Submitted->value)->count(),
                'approved' => BlogPost::query()->where('status', PublishStatus::Approved->value)->count(),
            ],
            'cms' => ['published' => Page::query()->where('status', PublishStatus::Published->value)->count(), 'draft' => Page::query()->where('status', PublishStatus::Draft->value)->count()],
            'home_hero' => ['slides' => HomeHeroSlide::query()->count(), 'active_slides' => HomeHeroSlide::query()->where('is_active', true)->count()],
            'home_hero_slides' => ['active' => HomeHeroSlide::query()->where('is_active', true)->count(), 'inactive' => HomeHeroSlide::query()->where('is_active', false)->count()],
            'home_page_sections' => ['active' => HomePageSection::query()->where('is_active', true)->count(), 'inactive' => HomePageSection::query()->where('is_active', false)->count()],
            'media' => ['public' => MediaAsset::query()->where('visibility', MediaVisibility::Public->value)->count(), 'private' => MediaAsset::query()->where('visibility', MediaVisibility::Private->value)->count()],
            'ad_zones' => ['active' => AdZone::query()->where('status', AdZoneStatus::Active->value)->count(), 'inactive' => AdZone::query()->where('status', AdZoneStatus::Inactive->value)->count()],
            'ad_campaigns' => ['active' => AdCampaign::query()->where('status', AdCampaignStatus::Active->value)->count(), 'paused' => AdCampaign::query()->where('status', AdCampaignStatus::Paused->value)->count()],
            'ad_creatives' => ['active' => AdCreative::query()->where('status', AdCreativeStatus::Active->value)->count(), 'paused' => AdCreative::query()->where('status', AdCreativeStatus::Paused->value)->count()],
            'advertiser_requests' => ['pending' => AdvertiserRequest::query()->where('status', AdvertiserRequestStatus::Pending->value)->count(), 'approved' => AdvertiserRequest::query()->where('status', AdvertiserRequestStatus::Approved->value)->count()],
            'ad_pricing' => ['active' => AdPricingSetting::query()->where('is_active', true)->count(), 'inactive' => AdPricingSetting::query()->where('is_active', false)->count()],
            'payment_products' => ['active' => PaymentProduct::query()->where('status', PaymentProductStatus::Active->value)->count(), 'archived' => PaymentProduct::query()->where('status', PaymentProductStatus::Archived->value)->count()],
            'payment_prices' => ['active' => PaymentPrice::query()->where('is_active', true)->count(), 'inactive' => PaymentPrice::query()->where('is_active', false)->count()],
            'payment_discounts' => ['active' => PaymentDiscount::query()->where('status', GrowthStatus::Active->value)->count(), 'launch' => PaymentDiscount::query()->where('is_launch_offer', true)->count()],
            'payment_checkouts' => ['ready' => PaymentCheckout::query()->where('status', PaymentCheckoutStatus::Ready->value)->count(), 'completed' => PaymentCheckout::query()->where('status', PaymentCheckoutStatus::Completed->value)->count()],
            'payment_orders' => ['completed' => PaymentOrder::query()->where('status', PaymentOrderStatus::Completed->value)->count(), 'pending' => PaymentOrder::query()->where('status', PaymentOrderStatus::Pending->value)->count()],
            'payment_subscriptions' => ['active' => PaymentSubscription::query()->where('status', PaymentSubscriptionStatus::Active->value)->count(), 'past_due' => PaymentSubscription::query()->where('status', PaymentSubscriptionStatus::PastDue->value)->count()],
            'team_accounts' => ['active' => TeamAccount::query()->where('status', TeamAccountStatus::Active->value)->count(), 'past_due' => TeamAccount::query()->where('status', TeamAccountStatus::PastDue->value)->count()],
            'team_seats' => ['active' => TeamSeat::query()->where('status', TeamSeatStatus::Active->value)->count(), 'invited' => TeamSeat::query()->where('status', TeamSeatStatus::Invited->value)->count()],
            'payment_reconciliation' => ['reconciled' => PaymentReconciliationRecord::query()->where('status', 'reconciled')->count(), 'pending' => PaymentReconciliationRecord::query()->where('status', 'pending')->count()],
            'gift_purchases' => ['purchased' => GiftPurchase::query()->where('status', GiftPurchaseStatus::Purchased->value)->count(), 'redeemed' => GiftPurchase::query()->where('status', GiftPurchaseStatus::Redeemed->value)->count()],
            'affiliate_partners' => ['active' => AffiliatePartner::query()->where('status', AffiliateStatus::Active->value)->count(), 'pending' => AffiliatePartner::query()->where('status', AffiliateStatus::Pending->value)->count()],
            'affiliate_visits' => ['total' => AffiliateVisit::query()->count(), 'active' => AffiliateVisit::query()->where('expires_at', '>=', now())->count()],
            'referral_conversions' => ['approved' => ReferralConversion::query()->where('status', ReferralConversionStatus::Approved->value)->count(), 'pending' => ReferralConversion::query()->where('status', ReferralConversionStatus::Pending->value)->count()],
            'checkout_recoveries' => ['open' => AbandonedCheckoutRecovery::query()->where('status', CheckoutRecoveryStatus::Open->value)->count(), 'recovered' => AbandonedCheckoutRecovery::query()->where('status', CheckoutRecoveryStatus::Recovered->value)->count()],
            'lead_magnets' => ['active' => LeadMagnet::query()->where('status', GrowthStatus::Active->value)->count(), 'draft' => LeadMagnet::query()->where('status', GrowthStatus::Draft->value)->count()],
            'newsletter_campaigns' => ['active' => NewsletterCampaign::query()->where('status', GrowthStatus::Active->value)->count(), 'draft' => NewsletterCampaign::query()->where('status', GrowthStatus::Draft->value)->count()],
            'lead_submissions' => ['subscribed' => LeadSubmission::query()->where('status', LeadSubmissionStatus::Subscribed->value)->count(), 'new' => LeadSubmission::query()->where('status', LeadSubmissionStatus::New->value)->count()],
            'ab_experiments' => ['active' => AbExperiment::query()->where('status', GrowthStatus::Active->value)->count(), 'paused' => AbExperiment::query()->where('status', GrowthStatus::Paused->value)->count()],
            'ab_variants' => ['total' => AbVariant::query()->count(), 'views' => (int) AbVariant::query()->sum('views_count')],
            'social_share_images' => ['active' => SocialShareImage::query()->where('status', GrowthStatus::Active->value)->count(), 'draft' => SocialShareImage::query()->where('status', GrowthStatus::Draft->value)->count()],
            'moderation_queue' => ['pending' => ModerationQueueItem::query()->where('status', CommunityContentStatus::Pending->value)->count(), 'spam' => ModerationQueueItem::query()->where('status', CommunityContentStatus::Spam->value)->count()],
            'content_reports' => ['open' => ContentReport::query()->where('status', CommunityReportStatus::Open->value)->count(), 'resolved' => ContentReport::query()->where('status', CommunityReportStatus::Resolved->value)->count()],
            'blocked_words' => ['active' => BlockedWord::query()->where('is_active', true)->count(), 'inactive' => BlockedWord::query()->where('is_active', false)->count()],
            'discussion_forums' => ['approved' => DiscussionForum::query()->where('status', CommunityContentStatus::Approved->value)->count(), 'pending' => DiscussionForum::query()->where('status', CommunityContentStatus::Pending->value)->count()],
            'discussion_threads' => ['approved' => DiscussionThread::query()->where('status', CommunityContentStatus::Approved->value)->count(), 'pending' => DiscussionThread::query()->where('status', CommunityContentStatus::Pending->value)->count()],
            'community_groups' => ['approved' => CommunityGroup::query()->where('status', CommunityContentStatus::Approved->value)->count(), 'paid' => CommunityGroup::query()->where('requires_paid_access', true)->count()],
            'trash' => [
                'courses' => Course::onlyTrashed()->count(),
                'blogs' => BlogPost::onlyTrashed()->count(),
            ],
            default => ['total' => $this->queryFactory->make($resource)->count()],
        };
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{label: string, value: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (\BackedEnum $case): array => [
            'label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(),
            'value' => (string) $case->value,
        ], $cases);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function standardPublishStatusOptions(): array
    {
        return $this->enumOptions([
            PublishStatus::Draft,
            PublishStatus::Pending,
            PublishStatus::Published,
            PublishStatus::Rejected,
            PublishStatus::Archived,
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function blogWorkflowStatusOptions(): array
    {
        return $this->enumOptions([
            PublishStatus::Draft,
            PublishStatus::Submitted,
            PublishStatus::Approved,
            PublishStatus::Published,
            PublishStatus::ChangesRequested,
            PublishStatus::Rejected,
            PublishStatus::DeleteRequested,
            PublishStatus::Trashed,
        ]);
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_values(array_map(fn (\BackedEnum $case): string => (string) $case->value, $cases));
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function roleOptions(): array
    {
        return Role::query()->orderBy('name')->get()->map(fn (Role $role): array => [
            'label' => $role->name,
            'value' => $role->name,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function permissionOptions(): array
    {
        return Permission::query()->orderBy('name')->get()->map(fn (Permission $permission): array => [
            'label' => $permission->name,
            'value' => $permission->name,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function userOptions(): array
    {
        return User::query()->orderBy('name')->get()->map(fn (User $user): array => [
            'label' => "{$user->name} ({$user->email})",
            'value' => $user->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function courseCategoryOptions(): array
    {
        return CourseCategory::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (CourseCategory $category): array => [
            'label' => $category->name.($category->is_active ? '' : ' (Inactive)'),
            'value' => $category->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string, parentValue: int}>
     */
    private function courseSubcategoryOptions(): array
    {
        return CourseSubcategory::query()->orderBy('course_category_id')->orderBy('sort_order')->orderBy('name')->get()->map(fn (CourseSubcategory $subcategory): array => [
            'label' => $subcategory->name.($subcategory->is_active ? '' : ' (Inactive)'),
            'value' => $subcategory->id,
            'parentValue' => $subcategory->course_category_id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function courseOptions(): array
    {
        return Course::query()->orderBy('title')->get()->map(fn (Course $course): array => [
            'label' => $course->title,
            'value' => $course->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function courseBundleOptions(): array
    {
        return CourseBundle::query()->orderBy('title')->get()->map(fn (CourseBundle $bundle): array => [
            'label' => $bundle->title,
            'value' => $bundle->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function courseSectionOptions(): array
    {
        return CourseSection::query()->with('course')->orderBy('title')->get()->map(fn (CourseSection $section): array => [
            'label' => trim(($section->course?->title ? $section->course->title.' / ' : '').$section->title),
            'value' => $section->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function discussionForumOptions(): array
    {
        return DiscussionForum::query()->with('course')->orderBy('title')->get()->map(fn (DiscussionForum $forum): array => [
            'label' => trim(($forum->course?->title ? $forum->course->title.' / ' : '').$forum->title),
            'value' => $forum->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function blogCategoryOptions(): array
    {
        return BlogCategory::query()->orderBy('name')->get()->map(fn (BlogCategory $category): array => [
            'label' => $category->name,
            'value' => $category->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function blogTagOptions(): array
    {
        return BlogTag::query()->orderBy('name')->get()->map(fn (BlogTag $tag): array => [
            'label' => $tag->name,
            'value' => $tag->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function mediaFolderOptions(): array
    {
        return MediaFolder::query()->orderBy('name')->get()->map(fn (MediaFolder $folder): array => [
            'label' => $folder->name,
            'value' => $folder->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string, url: string|null}>
     */
    private function mediaPickerOptions(): array
    {
        return MediaAsset::query()
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (MediaAsset $asset): array => [
                'label' => $asset->title ?: basename($asset->path),
                'value' => $asset->id,
                'url' => $asset->url,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function adZoneOptions(): array
    {
        return AdZone::query()->orderBy('name')->get()->map(fn (AdZone $zone): array => [
            'label' => $zone->name,
            'value' => $zone->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function adCampaignOptions(): array
    {
        return AdCampaign::query()->orderBy('name')->get()->map(fn (AdCampaign $campaign): array => [
            'label' => $campaign->name,
            'value' => $campaign->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function paymentProductOptions(): array
    {
        return PaymentProduct::query()->orderBy('name')->get()->map(fn (PaymentProduct $product): array => [
            'label' => $product->name,
            'value' => $product->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function paymentDiscountOptions(): array
    {
        return PaymentDiscount::query()->orderBy('name')->get()->map(fn (PaymentDiscount $discount): array => [
            'label' => $discount->code.' - '.$discount->name,
            'value' => $discount->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function instructorProfileOptions(): array
    {
        return InstructorProfile::query()
            ->orderBy('display_name')
            ->get()
            ->map(fn (InstructorProfile $profile): array => [
                'label' => $profile->display_name,
                'value' => $profile->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function editorialRevisionOptions(): array
    {
        return EditorialRevision::query()
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (EditorialRevision $revision): array => [
                'label' => '#'.$revision->id.' '.$revision->title,
                'value' => $revision->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function paymentPriceOptions(): array
    {
        return PaymentPrice::query()
            ->with('product')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentPrice $price): array => [
                'label' => trim(($price->product?->name ? $price->product->name.' / ' : '').$price->name.' ('.$price->formattedAmount().')'),
                'value' => $price->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function teamAccountOptions(): array
    {
        return TeamAccount::query()->orderBy('name')->get()->map(fn (TeamAccount $team): array => [
            'label' => $team->name,
            'value' => $team->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function paymentCheckoutOptions(): array
    {
        return PaymentCheckout::query()
            ->with(['user', 'price.product'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (PaymentCheckout $checkout): array => [
                'label' => trim('#'.$checkout->id.' '.$checkout->user?->email.' '.$checkout->price?->product?->name),
                'value' => $checkout->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function paymentOrderOptions(): array
    {
        return PaymentOrder::query()
            ->with('user')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (PaymentOrder $order): array => [
                'label' => trim('#'.$order->id.' '.$order->user?->email.' '.$order->paddle_transaction_id),
                'value' => $order->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function affiliatePartnerOptions(): array
    {
        return AffiliatePartner::query()->orderBy('name')->get()->map(fn (AffiliatePartner $partner): array => [
            'label' => $partner->name.' ('.$partner->code.')',
            'value' => $partner->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function affiliateVisitOptions(): array
    {
        return AffiliateVisit::query()
            ->with('partner')
            ->latest('clicked_at')
            ->limit(200)
            ->get()
            ->map(fn (AffiliateVisit $visit): array => [
                'label' => trim('#'.$visit->id.' '.$visit->partner?->code.' '.$visit->visitor_id),
                'value' => $visit->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function leadMagnetOptions(): array
    {
        return LeadMagnet::query()->orderBy('title')->get()->map(fn (LeadMagnet $leadMagnet): array => [
            'label' => $leadMagnet->title,
            'value' => $leadMagnet->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function newsletterCampaignOptions(): array
    {
        return NewsletterCampaign::query()->orderBy('name')->get()->map(fn (NewsletterCampaign $campaign): array => [
            'label' => $campaign->name,
            'value' => $campaign->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function abExperimentOptions(): array
    {
        return AbExperiment::query()->orderBy('name')->get()->map(fn (AbExperiment $experiment): array => [
            'label' => $experiment->name.' ('.$experiment->key.')',
            'value' => $experiment->id,
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    private function settingGroupOptions(): array
    {
        return SiteSetting::query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->map(fn (string $group): array => [
                'label' => str($group)->headline()->toString(),
                'value' => $group,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string, tone: string, method: string}>
     */
    private function blogWorkflowActions(BlogPost $post): array
    {
        $status = $this->statusValue($post->getAttribute('status'));

        if ($status === PublishStatus::Submitted->value) {
            return [
                $this->workflowAction('Approve', route('admin.blog-workflow.blogs.approve', $post, false), 'success'),
                $this->workflowAction('Changes', route('admin.blog-workflow.blogs.changes-requested', $post, false), 'warning'),
                $this->workflowAction('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        if ($status === PublishStatus::Approved->value) {
            return [
                $this->workflowAction('Publish', route('admin.blog-workflow.blogs.publish', $post, false), 'success'),
                $this->workflowAction('Changes', route('admin.blog-workflow.blogs.changes-requested', $post, false), 'warning'),
                $this->workflowAction('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        if ($status === PublishStatus::ChangesRequested->value) {
            return [
                $this->workflowAction('Approve', route('admin.blog-workflow.blogs.approve', $post, false), 'success'),
                $this->workflowAction('Reject', route('admin.blog-workflow.blogs.reject', $post, false), 'danger'),
            ];
        }

        if ($status === PublishStatus::DeleteRequested->value) {
            $deletionRequest = $post->deletionRequests
                ->first(fn (CreatorContentDeletionRequest $request): bool => $this->statusValue($request->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value);

            if ($deletionRequest instanceof CreatorContentDeletionRequest) {
                return [
                    $this->workflowAction('Trash', route('admin.blog-workflow.deletions.approve', $deletionRequest, false), 'danger'),
                    $this->workflowAction('Keep', route('admin.blog-workflow.deletions.reject', $deletionRequest, false), 'warning'),
                ];
            }
        }

        return [];
    }

    /**
     * @return array<int, array{label: string, url: string, tone: string, method: string}>
     */
    private function courseWorkflowActions(Course $course): array
    {
        $actions = [
            $this->workflowAction('Builder', route('admin.courses.builder.show', $course, false), 'default', 'get'),
        ];
        $status = $this->statusValue($course->getAttribute('status'));

        if ($status === PublishStatus::Pending->value) {
            return [
                $this->workflowAction('Approve', route('admin.course-workflow.courses.approve', $course, false), 'success'),
                $this->workflowAction('Changes', route('admin.course-workflow.courses.changes-requested', $course, false), 'warning'),
                $this->workflowAction('Reject', route('admin.course-workflow.courses.reject', $course, false), 'danger'),
                ...$actions,
            ];
        }

        if ($status === PublishStatus::DeleteRequested->value) {
            $deletionRequest = $course->deletionRequests
                ->first(fn (CreatorContentDeletionRequest $request): bool => $this->statusValue($request->getAttribute('status')) === CreatorContentDeletionStatus::Pending->value);

            if ($deletionRequest instanceof CreatorContentDeletionRequest) {
                return [
                    $this->workflowAction('Trash', route('admin.course-workflow.deletions.approve', $deletionRequest, false), 'danger'),
                    $this->workflowAction('Keep', route('admin.course-workflow.deletions.reject', $deletionRequest, false), 'warning'),
                    ...$actions,
                ];
            }
        }

        return $actions;
    }

    /**
     * @return array<int, array{label: string, value: string|null}>
     */
    private function courseReviewItems(Course $course): array
    {
        $creator = $course->creator;
        $profile = $creator?->bloggerProfile;

        return [
            [
                'label' => 'Creator Profile',
                'value' => $creator?->name
                    ? trim($creator->name.($profile?->linkedin_url ? ' / '.$profile->linkedin_url : ''))
                    : null,
            ],
            [
                'label' => 'Ownership Proof',
                'value' => $this->courseHasOwnershipProof($course)
                    ? ($course->ownershipVideo?->title ?: $course->ownership_video_url ?: 'Provided')
                    : null,
            ],
            [
                'label' => 'Curriculum',
                'value' => sprintf(
                    '%s sections, %s lessons, %s resources, %s FAQs',
                    $course->sections_count ?? 0,
                    $course->lessons_count ?? 0,
                    $course->resources_count ?? 0,
                    $course->faqs_count ?? 0,
                ),
            ],
            [
                'label' => 'Reviewer Scope',
                'value' => 'Course content, lessons, media/resources, ownership proof, creator profile',
            ],
        ];
    }

    private function guardCoursePublication(Course $course): void
    {
        if ($this->statusValue($course->getAttribute('status')) !== PublishStatus::Published->value) {
            return;
        }

        if (! $this->courseHasOwnershipProof($course)) {
            abort(422, 'Add the creator ownership proof before publishing this course.');
        }

        if (! $course->sections()->exists() || ! $course->lessons()->exists()) {
            abort(422, 'Add at least one section and one lesson before publishing this course.');
        }
    }

    private function courseHasOwnershipProof(Course $course): bool
    {
        return blank($course->ownership_statement) === false
            && (blank($course->ownership_video_url) === false || blank($course->ownership_video_media_id) === false);
    }

    /**
     * @return array<int, array{label: string, url: string, tone: string, method: string}>
     */
    private function editorialRevisionWorkflowActions(EditorialRevision $revision): array
    {
        $content = $revision->editorialable;

        if ($content instanceof BlogPost) {
            $routePrefix = 'admin.blog-workflow.revisions.';
        } elseif ($content instanceof Course) {
            $routePrefix = 'admin.course-workflow.revisions.';
        } else {
            return [];
        }

        $status = $this->statusValue($revision->getAttribute('status'));

        if ($status === EditorialRevisionStatus::Submitted->value) {
            return [
                $this->workflowAction('Apply', route($routePrefix.'approve', $revision, false), 'success'),
                $this->workflowAction('Changes', route($routePrefix.'changes-requested', $revision, false), 'warning'),
                $this->workflowAction('Reject', route($routePrefix.'reject', $revision, false), 'danger'),
            ];
        }

        if ($status === EditorialRevisionStatus::ChangesRequested->value) {
            return [
                $this->workflowAction('Apply', route($routePrefix.'approve', $revision, false), 'success'),
                $this->workflowAction('Reject', route($routePrefix.'reject', $revision, false), 'danger'),
            ];
        }

        return [];
    }

    /**
     * @return array{label: string, url: string, tone: string, method: string}
     */
    private function workflowAction(string $label, string $url, string $tone, string $method = 'post'): array
    {
        return compact('label', 'url', 'tone', 'method');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(Model $record): array
    {
        $histories = match (true) {
            $record instanceof BloggerProfile,
            $record instanceof InstructorProfile,
            $record instanceof Course,
            $record instanceof CourseLesson,
            $record instanceof BlogPost,
            $record instanceof EditorialRevision,
            $record instanceof Page => $record->approvalHistories,
            default => collect(),
        };

        return $histories->take(5)->map(fn ($history): array => [
            'decision' => $history->decision,
            'from_status' => $history->from_status,
            'to_status' => $history->to_status,
            'note' => $history->note,
            'actor' => $history->actor?->name,
            'created_at' => $history->created_at?->toDateTimeString(),
        ])->values()->all();
    }

    private function moneyString(string $currency, int $amount): string
    {
        return strtoupper($currency).' '.number_format($amount / 100, 2);
    }

    private function jsonContent(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function structuredValue(string $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['content' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingValue(string $value): array
    {
        if (blank($value)) {
            return ['content' => null];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['content' => $value];
    }

    /**
     * @return array<int, string>
     */
    private function homeHeroHighlightTerms(mixed $terms): array
    {
        if (is_string($terms)) {
            $terms = explode(',', $terms);
        }

        if (! is_array($terms)) {
            return ['courses', 'mentors'];
        }

        return collect($terms)
            ->map(fn (mixed $term): string => trim((string) $term))
            ->filter()
            ->values()
            ->all();
    }

    private function truthyValue(mixed $value): bool
    {
        return is_bool($value)
            ? $value
            : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function truthyLabel(mixed $value): string
    {
        return $this->truthyValue($value) ? 'Yes' : 'No';
    }

    private function statusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return is_scalar($status) ? (string) $status : null;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value) && filled($value)) {
            return Carbon::parse($value)->toDateString();
        }

        return null;
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

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
