<?php

namespace Modules\Admin\Services;

use App\Enums\LearningResourceAccess;
use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Enums\PublishStatus;
use App\Enums\QuizQuestionType;
use App\Enums\VideoType;
use App\Models\ApprovalHistory;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\MediaAsset;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Admin\ApprovalRecorder;
use App\Services\Admin\AuditLogger;
use App\Services\Content\CourseWorkflowService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\CourseBuilder\UpdatePublishingRequest;

final readonly class AdminCourseBuilderService
{
    /** @var list<string> */
    public const ITEM_KINDS = [
        'sections',
        'lessons',
        'resources',
        'faqs',
        'quizzes',
        'quiz-questions',
        'assignments',
        'prices',
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private ApprovalRecorder $approvalRecorder,
        private CourseWorkflowService $workflow,
        private ContentSanitizer $sanitizer,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Course $course): array
    {
        $course->load([
            'category',
            'subcategory',
            'creator',
            'thumbnail',
            'ownershipVideo',
            'sections.lessons',
            'lessons.section',
            'lessons.videoFile',
            'resources.lesson',
            'resources.mediaAsset',
            'faqs.lesson',
            'quizzes.lesson',
            'quizzes.questions',
            'assignments.lesson',
            'paymentProduct.prices',
            'approvalHistories.actor',
        ]);

        return [
            'course' => $this->coursePayload($course),
            'sections' => $course->sections->sortBy('sort_order')->values()->map(fn (CourseSection $section): array => $this->sectionPayload($course, $section))->all(),
            'lessons' => $course->lessons->sortBy('order_number')->values()->map(fn (CourseLesson $lesson): array => $this->lessonPayload($course, $lesson))->all(),
            'resources' => $course->resources->sortBy('sort_order')->values()->map(fn (CourseResource $resource): array => $this->resourcePayload($course, $resource))->all(),
            'faqs' => $course->faqs->sortBy('sort_order')->values()->map(fn (CourseFaq $faq): array => $this->faqPayload($course, $faq))->all(),
            'quizzes' => $course->quizzes->sortBy('sort_order')->values()->map(fn (Quiz $quiz): array => $this->quizPayload($course, $quiz))->all(),
            'assignments' => $course->assignments->sortBy('sort_order')->values()->map(fn (Assignment $assignment): array => $this->assignmentPayload($course, $assignment))->all(),
            'pricing' => $this->pricingPayload($course),
            'approval_history' => $course->approvalHistories->sortByDesc('id')->values()->map(fn (ApprovalHistory $history): array => [
                'id' => $history->id,
                'decision' => $history->decision,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'note' => $history->note,
                'actor' => $history->actor?->name,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->all(),
            'readiness_issues' => $this->workflow->readinessIssues($course),
            'options' => $this->options(),
            'urls' => [
                'index' => route('admin.courses.index', absolute: false),
                'update' => route('admin.courses.builder.update', $course, absolute: false),
                'ownership' => route('admin.courses.builder.ownership', $course, absolute: false),
                'publishing' => route('admin.courses.builder.publishing', $course, absolute: false),
                'product' => route('admin.courses.builder.product', $course, absolute: false),
                'items' => route('admin.courses.builder.items.store', [$course, 'kind' => '__kind__'], absolute: false),
                'reorder' => route('admin.courses.builder.reorder', $course, absolute: false),
                'public' => $course->isPublished() ? route('public.courses.show', $course->slug, false) : null,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateCourse(Request $request, Course $course, array $data): Course
    {
        if (is_string($data['schema'] ?? null) && filled($data['schema'])) {
            $data['schema'] = json_decode($data['schema'], true, 512, JSON_THROW_ON_ERROR);
        } else {
            $data['schema'] = null;
        }

        return $this->updateModel($request, $course, 'course_updated', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateOwnership(Request $request, Course $course, array $data): Course
    {
        unset($data['confirmed']);
        $data['ownership_confirmed_at'] = now();

        return $this->updateModel($request, $course, 'ownership_updated', $data);
    }

    /** @param array<string, mixed> $data */
    public function updatePublishing(Request $request, Course $course, array $data): Course
    {
        $to = PublishStatus::from((string) $data['status']);
        $from = $course->getAttribute('status');
        $fromValue = $this->enumValue($from);

        if ($to === PublishStatus::Published || $fromValue === PublishStatus::Published->value) {
            abort_unless($request->user()?->can('admin.content.publish'), 403);
        }

        if ($to === PublishStatus::Published) {
            $issues = $this->workflow->readinessIssues($course);
            if ($issues !== []) {
                throw ValidationException::withMessages(['status' => implode(' ', $issues)]);
            }
        }

        if ($fromValue === PublishStatus::Pending->value) {
            if ($to === PublishStatus::Published) {
                $updated = $this->workflow->approve($request, $course, $data['admin_notes'] ?? null);
                $this->log($request, 'publishing_updated', $updated, ['status' => $fromValue]);

                return $updated;
            }

            if ($to === PublishStatus::ChangesRequested) {
                return $this->workflow->requestChanges($request, $course, $data['admin_notes'] ?? null);
            }

            if ($to === PublishStatus::Rejected) {
                return $this->workflow->reject($request, $course, $data['rejection_reason'] ?? $data['admin_notes'] ?? null);
            }
        }

        $before = $course->toArray();
        $course->forceFill([
            'status' => $to,
            'published_at' => $to === PublishStatus::Published ? ($course->published_at ?: now()) : null,
            'admin_notes' => $data['admin_notes'] ?? null,
            'rejection_reason' => in_array($to, [PublishStatus::Rejected, PublishStatus::ChangesRequested], true)
                ? ($data['rejection_reason'] ?? $data['admin_notes'] ?? null)
                : null,
        ])->save();

        if ($to === PublishStatus::Published) {
            $publishedAt = $course->published_at ?: now();
            $course->sections()->update(['status' => PublishStatus::Published->value, 'published_at' => $publishedAt]);
            $course->lessons()->update(['status' => PublishStatus::Published->value, 'published_at' => $publishedAt]);
            $course->faqs()->update(['status' => PublishStatus::Published->value, 'published_at' => $publishedAt]);
        }

        $this->approvalRecorder->record(
            $request,
            $course,
            'admin_builder_status_updated',
            $from,
            $to,
            $data['admin_notes'] ?? $data['rejection_reason'] ?? null,
        );
        $this->log($request, 'publishing_updated', $course, $before);

        return $course;
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(Request $request, Course $course, array $data): Course
    {
        return DB::transaction(function () use ($request, $course, $data): Course {
            $courseBefore = $course->toArray();
            $isFree = (bool) $data['is_free'];
            $course->forceFill([
                'is_free' => $isFree,
                'price' => $isFree ? 0 : ($data['price'] ?? 0),
            ])->save();
            $this->log($request, 'pricing_updated', $course, $courseBefore);

            $product = $course->paymentProduct;

            if ($isFree) {
                if ($product instanceof PaymentProduct) {
                    $before = $product->toArray();
                    $product->status = PaymentProductStatus::Archived;
                    $product->save();
                    $this->log($request, 'product_archived', $product, $before);
                }

                return $course;
            }

            $product ??= new PaymentProduct([
                'course_id' => $course->id,
                'type' => PaymentProductType::Course,
                'status' => PaymentProductStatus::Active,
            ]);
            $before = $product->exists ? $product->toArray() : null;
            $product->fill(Arr::only($data, [
                'name', 'description', 'type', 'status', 'paddle_product_id', 'tax_category',
            ]));
            $product->course_id = $course->id;
            $product->save();
            $this->log($request, 'product_updated', $product, $before);

            return $course;
        });
    }

    /** @param array<string, mixed> $data */
    public function storeItem(Request $request, Course $course, string $kind, array $data): Model
    {
        $this->guardKind($kind);
        $attributes = $this->normalizeItemData($kind, $data);

        $record = DB::transaction(function () use ($course, $kind, $attributes): Model {
            return match ($kind) {
                'sections' => $course->sections()->create($attributes),
                'lessons' => $course->lessons()->create($attributes),
                'resources' => $course->resources()->create($attributes),
                'faqs' => $course->faqs()->create($attributes),
                'quizzes' => $course->quizzes()->create($attributes),
                'assignments' => $course->assignments()->create($attributes),
                'quiz-questions' => Quiz::query()->where('course_id', $course->id)->findOrFail($attributes['quiz_id'])->questions()->create(Arr::except($attributes, 'quiz_id')),
                'prices' => $this->productFor($course)->prices()->create($attributes),
                default => throw ValidationException::withMessages(['kind' => 'Unsupported builder item.']),
            };
        });

        $this->log($request, "{$kind}_created", $record, null);

        return $record;
    }

    /** @param array<string, mixed> $data */
    public function updateItem(Request $request, Course $course, string $kind, int $id, array $data): Model
    {
        $record = $this->findItem($course, $kind, $id);
        $before = $record->toArray();
        $record->fill($this->normalizeItemData($kind, $data));
        $record->save();
        $this->log($request, "{$kind}_updated", $record, $before);

        return $record;
    }

    public function deleteItem(Request $request, Course $course, string $kind, int $id): void
    {
        $record = $this->findItem($course, $kind, $id);
        $before = $record->toArray();
        $record->delete();
        $this->log($request, "{$kind}_deleted", $record, $before);
    }

    /** @param list<int> $ids */
    public function reorder(Request $request, Course $course, string $type, array $ids): void
    {
        $relation = $type === 'sections' ? $course->sections() : $course->lessons();
        $column = $type === 'sections' ? 'sort_order' : 'order_number';
        $records = $relation->whereKey($ids)->get()->keyBy(fn (Model $model) => $model->getKey());

        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Every reordered item must belong to this course.']);
        }

        DB::transaction(function () use ($request, $type, $ids, $records, $column): void {
            foreach ($ids as $index => $id) {
                $record = $records->get($id);
                $before = $record->toArray();
                $record->setAttribute($column, $index);
                $record->save();
                $this->log($request, "{$type}_reordered", $record, $before);
            }
        });
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'categories' => CourseCategory::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (CourseCategory $category): array => [
                'label' => $category->name,
                'value' => $category->id,
            ])->all(),
            'subcategories' => CourseSubcategory::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (CourseSubcategory $subcategory): array => [
                'label' => $subcategory->name,
                'value' => $subcategory->id,
                'parentValue' => $subcategory->course_category_id,
            ])->all(),
            'creators' => User::query()->orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $user): array => [
                'label' => "{$user->name} ({$user->email})",
                'value' => $user->id,
            ])->all(),
            'media' => MediaAsset::query()->latest()->limit(100)->get()->map(fn (MediaAsset $asset): array => [
                'label' => $asset->title ?: basename($asset->path),
                'value' => $asset->id,
                'url' => $asset->url,
            ])->all(),
            'publish_statuses' => $this->enumOptions(array_map(
                static fn (string $status): PublishStatus => PublishStatus::from($status),
                UpdatePublishingRequest::ALLOWED_STATUSES,
            )),
            'video_types' => $this->enumOptions(VideoType::cases()),
            'resource_access' => $this->enumOptions(LearningResourceAccess::cases()),
            'question_types' => $this->enumOptions(QuizQuestionType::cases()),
            'product_types' => $this->enumOptions(PaymentProductType::cases()),
            'product_statuses' => $this->enumOptions(PaymentProductStatus::cases()),
            'billing_intervals' => $this->enumOptions(PaymentBillingInterval::cases()),
        ];
    }

    /** @return array<string, mixed> */
    private function coursePayload(Course $course): array
    {
        return [
            ...Arr::only($course->toArray(), [
                'id', 'title', 'slug', 'course_category_id', 'course_subcategory_id', 'created_by',
                'thumbnail_media_id', 'short_description', 'description', 'intro_video_url', 'level',
                'language', 'price', 'is_free', 'status', 'published_at', 'admin_notes', 'rejection_reason',
                'seo_title', 'seo_description', 'seo_image', 'canonical_url', 'ownership_video_media_id',
                'ownership_video_url', 'ownership_statement', 'ownership_confirmed_at',
            ]),
            'status' => $this->enumValue($course->status),
            'schema' => $course->schema === null ? null : json_encode($course->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'creator_name' => $course->creator?->name,
            'ownership_complete' => $this->workflow->hasOwnershipProof($course),
        ];
    }

    /** @return array<string, mixed> */
    private function sectionPayload(Course $course, CourseSection $section): array
    {
        return [
            ...Arr::only($section->toArray(), ['id', 'title', 'description', 'sort_order']),
            'status' => $this->enumValue($section->status),
            ...$this->itemUrls($course, 'sections', $section->id),
        ];
    }

    /** @return array<string, mixed> */
    private function lessonPayload(Course $course, CourseLesson $lesson): array
    {
        return [
            ...Arr::only($lesson->toArray(), [
                'id', 'course_section_id', 'title', 'order_number', 'content', 'video_url', 'video_file_id',
                'is_free', 'is_paid', 'preview_word_limit', 'seo_title', 'seo_description',
            ]),
            'video_type' => $this->enumValue($lesson->video_type),
            'status' => $this->enumValue($lesson->status),
            ...$this->itemUrls($course, 'lessons', $lesson->id),
        ];
    }

    /** @return array<string, mixed> */
    private function resourcePayload(Course $course, CourseResource $resource): array
    {
        return [
            ...Arr::only($resource->toArray(), [
                'id', 'course_lesson_id', 'media_asset_id', 'title', 'description', 'type', 'file_path',
                'external_url', 'is_downloadable', 'is_active', 'sort_order',
            ]),
            'access_level' => $this->enumValue($resource->access_level),
            ...$this->itemUrls($course, 'resources', $resource->id),
        ];
    }

    /** @return array<string, mixed> */
    private function faqPayload(Course $course, CourseFaq $faq): array
    {
        return [
            ...Arr::only($faq->toArray(), ['id', 'course_lesson_id', 'question', 'answer', 'sort_order']),
            'status' => $this->enumValue($faq->status),
            ...$this->itemUrls($course, 'faqs', $faq->id),
        ];
    }

    /** @return array<string, mixed> */
    private function quizPayload(Course $course, Quiz $quiz): array
    {
        return [
            ...Arr::only($quiz->toArray(), [
                'id', 'course_lesson_id', 'title', 'description', 'pass_score', 'max_attempts',
                'time_limit_minutes', 'is_required', 'is_active', 'sort_order',
            ]),
            'questions' => $quiz->questions->sortBy('sort_order')->values()->map(fn (QuizQuestion $question): array => [
                ...Arr::only($question->toArray(), ['id', 'question', 'points', 'explanation', 'sort_order']),
                'quiz_id' => $quiz->id,
                'type' => $this->enumValue($question->type),
                'options_json' => json_encode($question->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'correct_answer_json' => json_encode($question->correct_answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ...$this->itemUrls($course, 'quiz-questions', $question->id),
            ])->all(),
            ...$this->itemUrls($course, 'quizzes', $quiz->id),
        ];
    }

    /** @return array<string, mixed> */
    private function assignmentPayload(Course $course, Assignment $assignment): array
    {
        return [
            ...Arr::only($assignment->toArray(), [
                'id', 'course_lesson_id', 'title', 'instructions', 'pass_score', 'max_points',
                'due_days_after_enrollment', 'allow_file_uploads', 'is_required', 'is_active', 'sort_order',
            ]),
            ...$this->itemUrls($course, 'assignments', $assignment->id),
        ];
    }

    /** @return array<string, mixed> */
    private function pricingPayload(Course $course): array
    {
        $product = $course->paymentProduct;

        return [
            'is_free' => (bool) $course->is_free,
            'price' => $course->price,
            'product' => $product ? [
                ...Arr::only($product->toArray(), [
                    'id', 'name', 'description', 'paddle_product_id', 'tax_category',
                ]),
                'type' => $this->enumValue($product->type),
                'status' => $this->enumValue($product->status),
            ] : null,
            'prices' => $product?->prices->map(fn (PaymentPrice $price): array => [
                ...Arr::only($price->toArray(), [
                    'id', 'name', 'paddle_price_id', 'is_recurring', 'currency', 'amount', 'trial_days', 'is_active',
                ]),
                'billing_interval' => $this->enumValue($price->billing_interval),
                ...$this->itemUrls($course, 'prices', $price->id),
            ])->all() ?? [],
        ];
    }

    /** @return array{update_url: string, delete_url: string} */
    private function itemUrls(Course $course, string $kind, int $id): array
    {
        return [
            'update_url' => route('admin.courses.builder.items.update', [$course, 'kind' => $kind, 'id' => $id], false),
            'delete_url' => route('admin.courses.builder.items.destroy', [$course, 'kind' => $kind, 'id' => $id], false),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalizeItemData(string $kind, array $data): array
    {
        if ($kind === 'lessons') {
            $data['content'] = $this->sanitizer->richText(is_string($data['content'] ?? null) ? $data['content'] : null);
            $this->syncPublishedData($data);
        }

        if (in_array($kind, ['sections', 'faqs'], true)) {
            $this->syncPublishedData($data);
        }

        if ($kind === 'assignments') {
            $data['instructions'] = $this->sanitizer->richText(is_string($data['instructions'] ?? null) ? $data['instructions'] : null);
        }

        if ($kind === 'quiz-questions') {
            $data['options'] = filled($data['options_json'] ?? null)
                ? json_decode((string) $data['options_json'], true, 512, JSON_THROW_ON_ERROR)
                : null;
            $data['correct_answer'] = json_decode((string) $data['correct_answer_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($data['options_json'], $data['correct_answer_json']);
        }

        if ($kind === 'prices') {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function syncPublishedData(array &$data): void
    {
        $data['published_at'] = ($data['status'] ?? null) === PublishStatus::Published->value ? now() : null;
    }

    private function productFor(Course $course): PaymentProduct
    {
        $product = $course->paymentProduct()->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product' => 'Save the paid product settings before adding a price plan.',
            ]);
        }

        return $product;
    }

    private function findItem(Course $course, string $kind, int $id): Model
    {
        $this->guardKind($kind);

        return match ($kind) {
            'sections' => $course->sections()->findOrFail($id),
            'lessons' => $course->lessons()->findOrFail($id),
            'resources' => $course->resources()->findOrFail($id),
            'faqs' => $course->faqs()->findOrFail($id),
            'quizzes' => $course->quizzes()->findOrFail($id),
            'assignments' => $course->assignments()->findOrFail($id),
            'quiz-questions' => QuizQuestion::query()->whereHas('quiz', fn ($query) => $query->where('course_id', $course->id))->findOrFail($id),
            'prices' => PaymentPrice::query()->whereHas('product', fn ($query) => $query->where('course_id', $course->id))->findOrFail($id),
            default => throw ValidationException::withMessages(['kind' => 'Unsupported builder item.']),
        };
    }

    private function guardKind(string $kind): void
    {
        abort_unless(in_array($kind, self::ITEM_KINDS, true), 404);
    }

    /** @param array<string, mixed> $data */
    private function updateModel(Request $request, Model $model, string $action, array $data): Model
    {
        $before = $model->toArray();
        $model->fill($data);
        $model->save();
        $this->log($request, $action, $model, $before);

        return $model;
    }

    /** @param array<string, mixed>|null $before */
    private function log(Request $request, string $action, Model $model, ?array $before): void
    {
        $this->auditLogger->log(
            $request,
            "admin.course_builder.{$action}",
            $model,
            $before,
            $model->fresh()?->toArray(),
        );
    }

    /** @param array<int, BackedEnum> $cases @return array<int, array{label: string, value: string|int}> */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => [
            'label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(),
            'value' => $case->value,
        ], $cases);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
