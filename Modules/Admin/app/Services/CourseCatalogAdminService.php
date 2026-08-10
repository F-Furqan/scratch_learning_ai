<?php

namespace Modules\Admin\Services;

use App\Enums\LearningResourceAccess;
use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CourseCatalogAdminService
{
    /** @var list<string> */
    public const RESOURCES = [
        'course_categories',
        'course_subcategories',
        'course_sections',
        'course_resources',
        'course_faqs',
    ];

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function reorderable(string $resource): bool
    {
        return $this->supports($resource);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'course_categories' => ['name', 'slug', 'status', 'courses_count', 'subcategories_count', 'sort_order'],
            'course_subcategories' => ['name', 'category', 'slug', 'status', 'courses_count', 'sort_order'],
            'course_sections' => ['title', 'course', 'status', 'lessons_count', 'sort_order'],
            'course_resources' => ['title', 'course', 'lesson', 'type', 'access_level', 'status', 'sort_order'],
            'course_faqs' => ['question', 'course', 'lesson', 'status', 'sort_order'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(string $resource): array
    {
        return match ($resource) {
            'course_categories' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
                ...$this->seoFields(),
            ],
            'course_subcategories' => [
                $this->field('course_category_id', 'Parent Category', 'select', $this->categoryOptions(), true),
                $this->field('name', 'Name', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
                ...$this->seoFields(),
            ],
            'course_sections' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('status', 'Status', 'select', $this->publishStatusOptions(), true),
            ],
            'course_resources' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->dependentField(
                    'course_lesson_id',
                    'Lesson',
                    $this->lessonOptions(),
                    'course_id',
                    '/admin/catalog/courses/{value}/lessons',
                ),
                $this->field('media_asset_id', 'Media Asset', 'media'),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('type', 'Type', 'select', [
                    ['label' => 'Download', 'value' => 'download'],
                    ['label' => 'Link', 'value' => 'link'],
                    ['label' => 'Document', 'value' => 'document'],
                    ['label' => 'Template', 'value' => 'template'],
                ], true),
                $this->field('access_level', 'Access Level', 'select', $this->enumOptions(LearningResourceAccess::cases()), true),
                $this->field('file_path', 'File Path', 'text'),
                $this->field('external_url', 'External URL', 'url'),
                $this->field('is_downloadable', 'Downloadable', 'checkbox'),
                $this->field('is_active', 'Active', 'checkbox'),
                $this->field('sort_order', 'Sort Order', 'number'),
            ],
            'course_faqs' => [
                $this->field('course_id', 'Course', 'select', $this->courseOptions(), true),
                $this->dependentField(
                    'course_lesson_id',
                    'Lesson',
                    $this->lessonOptions(),
                    'course_id',
                    '/admin/catalog/courses/{value}/lessons',
                ),
                $this->field('question', 'Question', 'text', required: true),
                $this->field('answer', 'Answer', 'textarea', required: true),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('status', 'Status', 'select', $this->publishStatusOptions(), true),
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];

        if (in_array($resource, ['course_categories', 'course_subcategories', 'course_resources'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->activeOptions());
        }

        if (in_array($resource, ['course_sections', 'course_faqs'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->publishStatusOptions());
        }

        if ($resource === 'course_subcategories') {
            $filters[] = $this->field('category', 'Parent Category', 'select', $this->categoryOptions());
        }

        if (in_array($resource, ['course_sections', 'course_resources', 'course_faqs'], true)) {
            $filters[] = $this->field('category', 'Course', 'select', $this->courseOptions());
        }

        return $filters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bulkActions(string $resource): array
    {
        return match ($resource) {
            'course_categories' => [
                $this->bulkAction('active', 'Set Active State', $this->activeOptions()),
                $this->bulkAction('reassign_delete', 'Reassign Courses And Delete', $this->categoryOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            'course_subcategories' => [
                $this->bulkAction('active', 'Set Active State', $this->activeOptions()),
                $this->bulkAction('reassign_delete', 'Reassign Courses And Delete', $this->subcategoryOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            'course_resources' => [
                $this->bulkAction('active', 'Set Active State', $this->activeOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            'course_sections', 'course_faqs' => [
                $this->bulkAction('status', 'Set Status', $this->publishStatusOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, int>
     */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'course_categories' => [
                'total' => CourseCategory::query()->count(),
                'active' => CourseCategory::query()->where('is_active', true)->count(),
            ],
            'course_subcategories' => [
                'total' => CourseSubcategory::query()->count(),
                'active' => CourseSubcategory::query()->where('is_active', true)->count(),
            ],
            'course_sections' => [
                'total' => CourseSection::query()->count(),
                'published' => CourseSection::query()->where('status', PublishStatus::Published->value)->count(),
            ],
            'course_resources' => [
                'total' => CourseResource::query()->count(),
                'active' => CourseResource::query()->where('is_active', true)->count(),
            ],
            'course_faqs' => [
                'total' => CourseFaq::query()->count(),
                'published' => CourseFaq::query()->where('status', PublishStatus::Published->value)->count(),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'course_categories' => $this->categoryRow($record),
            'course_subcategories' => $this->subcategoryRow($record),
            'course_sections' => $this->sectionRow($record),
            'course_resources' => $this->resourceRow($record),
            'course_faqs' => $this->faqRow($record),
            default => [],
        };
    }

    public function persist(Request $request, string $resource, ?Model $record = null): Model
    {
        return match ($resource) {
            'course_categories' => $this->persistCategory($request, $record),
            'course_subcategories' => $this->persistSubcategory($request, $record),
            'course_sections' => $this->persistSection($request, $record),
            'course_resources' => $this->persistResource($request, $record),
            'course_faqs' => $this->persistFaq($request, $record),
            default => throw ValidationException::withMessages(['resource' => 'Unsupported catalog resource.']),
        };
    }

    /** @param array<string, mixed> $data */
    public function validateBulk(Request $request, string $resource, array $data): void
    {
        if ($data['action'] !== 'reassign_delete') {
            return;
        }

        abort_unless($request->user()?->can('admin.catalog.reassign'), 403);

        $targetId = (int) ($data['value'] ?? 0);
        $selectedIds = array_map('intval', is_array($data['ids'] ?? null) ? $data['ids'] : []);

        if ($targetId < 1 || in_array($targetId, $selectedIds, true)) {
            throw ValidationException::withMessages([
                'value' => 'Choose a replacement that is not selected for deletion.',
            ]);
        }

        $exists = match ($resource) {
            'course_categories' => CourseCategory::query()->whereKey($targetId)->exists(),
            'course_subcategories' => CourseSubcategory::query()->whereKey($targetId)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages(['value' => 'The replacement no longer exists.']);
        }
    }

    public function applyBulkMutation(string $resource, Model $record, string $action, ?string $value): bool
    {
        if ($action === 'active' && in_array($resource, ['course_categories', 'course_subcategories', 'course_resources'], true)) {
            $record->setAttribute('is_active', $value === 'active');
            $record->save();

            return true;
        }

        if ($action === 'status' && in_array($resource, ['course_sections', 'course_faqs'], true)) {
            $record->setAttribute('status', $value);
            $this->syncPublishedAt($record);
            $record->save();

            return true;
        }

        if ($action === 'reassign_delete' && $record instanceof CourseCategory) {
            $this->reassignCategoryAndDelete($record, (int) $value);

            return true;
        }

        if ($action === 'reassign_delete' && $record instanceof CourseSubcategory) {
            $this->reassignSubcategoryAndDelete($record, (int) $value);

            return true;
        }

        return false;
    }

    public function guardDeletion(string $resource, Model $record): void
    {
        if ($record instanceof CourseCategory && ($record->courses()->exists() || $record->subcategories()->exists())) {
            throw ValidationException::withMessages([
                'delete' => 'This category is in use. Select it and use “Reassign Courses And Delete”.',
            ]);
        }

        if ($record instanceof CourseSubcategory && $record->courses()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'This subcategory is in use. Select it and use “Reassign Courses And Delete”.',
            ]);
        }

        if ($record instanceof CourseSection && $record->lessons()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Move or delete this section’s lessons before deleting the section.',
            ]);
        }
    }

    /** @return class-string<Model> */
    public function modelClass(string $resource): string
    {
        return match ($resource) {
            'course_categories' => CourseCategory::class,
            'course_subcategories' => CourseSubcategory::class,
            'course_sections' => CourseSection::class,
            'course_resources' => CourseResource::class,
            'course_faqs' => CourseFaq::class,
            default => throw ValidationException::withMessages(['resource' => 'Unsupported catalog resource.']),
        };
    }

    /** @return array<int, array{label: string, value: int, parentValue: int}> */
    public function subcategoriesFor(CourseCategory $category): array
    {
        return $category->subcategories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CourseSubcategory $subcategory): array => [
                'label' => $subcategory->name.($subcategory->is_active ? '' : ' (Inactive)'),
                'value' => $subcategory->id,
                'parentValue' => $category->id,
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int, parentValue: int}> */
    public function lessonsFor(Course $course): array
    {
        return $course->lessons()
            ->orderBy('order_number')
            ->orderBy('title')
            ->get()
            ->map(fn (CourseLesson $lesson): array => [
                'label' => $lesson->title,
                'value' => $lesson->id,
                'parentValue' => $course->id,
            ])
            ->all();
    }

    private function persistCategory(Request $request, ?Model $record): CourseCategory
    {
        $category = $record instanceof CourseCategory ? $record : new CourseCategory;
        $data = $this->validateTaxonomy($request);
        $category->fill($this->normalizeTaxonomyData($data));
        $category->save();

        return $category;
    }

    private function persistSubcategory(Request $request, ?Model $record): CourseSubcategory
    {
        $subcategory = $record instanceof CourseSubcategory ? $record : new CourseSubcategory;
        $data = $this->validateTaxonomy($request, [
            'course_category_id' => [
                'required',
                Rule::exists('course_categories', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $subcategory->fill($this->normalizeTaxonomyData($data));

        if ($subcategory->exists && $subcategory->isDirty('course_category_id')) {
            $subcategory->slug = null;
        }

        $subcategory->save();

        return $subcategory;
    }

    private function persistSection(Request $request, ?Model $record): CourseSection
    {
        $section = $record instanceof CourseSection ? $record : new CourseSection;
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['required', Rule::in($this->enumValues(PublishStatus::cases()))],
        ]);

        $section->fill($data);

        if ($section->exists && $section->isDirty('course_id')) {
            $section->slug = null;
        }

        $this->syncPublishedAt($section);
        $section->save();

        return $section;
    }

    private function persistResource(Request $request, ?Model $record): CourseResource
    {
        $resource = $record instanceof CourseResource ? $record : new CourseResource;
        $courseId = $request->integer('course_id');
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'course_lesson_id' => [
                'nullable',
                Rule::exists('course_lessons', 'id')->where(fn ($query) => $query->where('course_id', $courseId)),
            ],
            'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['download', 'link', 'document', 'template'])],
            'access_level' => ['required', Rule::in($this->enumValues(LearningResourceAccess::cases()))],
            'file_path' => ['nullable', 'string', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'is_downloadable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $resource->fill($data);
        $resource->save();

        return $resource;
    }

    private function persistFaq(Request $request, ?Model $record): CourseFaq
    {
        $faq = $record instanceof CourseFaq ? $record : new CourseFaq;
        $courseId = $request->integer('course_id');
        $data = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'course_lesson_id' => [
                'nullable',
                Rule::exists('course_lessons', 'id')->where(fn ($query) => $query->where('course_id', $courseId)),
            ],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'status' => ['required', Rule::in($this->enumValues(PublishStatus::cases()))],
        ]);

        $faq->fill($data);
        $this->syncPublishedAt($faq);
        $faq->save();

        return $faq;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateTaxonomy(Request $request, array $extra = []): array
    {
        return $request->validate([
            ...$extra,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_image' => ['nullable', 'url', 'max:2048'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'schema' => ['nullable', 'json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTaxonomyData(array $data): array
    {
        if (is_string($data['schema'] ?? null) && filled($data['schema'])) {
            $data['schema'] = json_decode($data['schema'], true, 512, JSON_THROW_ON_ERROR);
        } else {
            $data['schema'] = null;
        }

        return $data;
    }

    private function reassignCategoryAndDelete(CourseCategory $category, int $replacementId): void
    {
        $replacement = CourseCategory::query()->findOrFail($replacementId);

        Course::query()
            ->where('course_category_id', $category->id)
            ->update([
                'course_category_id' => $replacement->id,
                'course_subcategory_id' => null,
            ]);

        $category->subcategories()->eachById(fn (CourseSubcategory $subcategory) => $subcategory->delete());
        $category->delete();
    }

    private function reassignSubcategoryAndDelete(CourseSubcategory $subcategory, int $replacementId): void
    {
        $replacement = CourseSubcategory::query()->findOrFail($replacementId);

        Course::query()
            ->where('course_subcategory_id', $subcategory->id)
            ->update([
                'course_category_id' => $replacement->course_category_id,
                'course_subcategory_id' => $replacement->id,
            ]);

        $subcategory->delete();
    }

    /** @return array<string, mixed> */
    private function categoryRow(Model $record): array
    {
        /** @var CourseCategory $record */
        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'courses_count' => $record->courses_count ?? $record->courses()->count(),
            'subcategories_count' => $record->subcategories_count ?? $record->subcategories()->count(),
            'sort_order' => $record->sort_order,
            'form' => $this->taxonomyForm($record),
        ];
    }

    /** @return array<string, mixed> */
    private function subcategoryRow(Model $record): array
    {
        /** @var CourseSubcategory $record */
        return [
            'id' => $record->id,
            'name' => $record->name,
            'category' => $record->category?->name,
            'slug' => $record->slug,
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'courses_count' => $record->courses_count ?? $record->courses()->count(),
            'sort_order' => $record->sort_order,
            'form' => ['course_category_id' => $record->course_category_id] + $this->taxonomyForm($record),
        ];
    }

    /** @return array<string, mixed> */
    private function sectionRow(Model $record): array
    {
        /** @var CourseSection $record */
        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'status' => $this->enumValue($record->status),
            'lessons_count' => $record->lessons_count ?? $record->lessons()->count(),
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), [
                'course_id', 'title', 'description', 'sort_order',
            ]) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /** @return array<string, mixed> */
    private function resourceRow(Model $record): array
    {
        /** @var CourseResource $record */
        return [
            'id' => $record->id,
            'title' => $record->title,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'type' => str($record->type)->headline()->toString(),
            'access_level' => str($this->enumValue($record->access_level))->headline()->toString(),
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), [
                'course_id', 'course_lesson_id', 'media_asset_id', 'title', 'description',
                'type', 'file_path', 'external_url', 'is_downloadable', 'is_active', 'sort_order',
            ]) + ['access_level' => $this->enumValue($record->access_level)],
        ];
    }

    /** @return array<string, mixed> */
    private function faqRow(Model $record): array
    {
        /** @var CourseFaq $record */
        return [
            'id' => $record->id,
            'question' => $record->question,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'status' => $this->enumValue($record->status),
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), [
                'course_id', 'course_lesson_id', 'question', 'answer', 'sort_order',
            ]) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /** @return array<string, mixed> */
    private function taxonomyForm(CourseCategory|CourseSubcategory $record): array
    {
        return Arr::only($record->toArray(), [
            'name', 'description', 'sort_order', 'is_active', 'seo_title',
            'seo_description', 'seo_image', 'canonical_url',
        ]) + [
            'schema' => $record->schema === null
                ? null
                : json_encode($record->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    private function categoryOptions(): array
    {
        return CourseCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CourseCategory $category): array => [
                'label' => $category->name.($category->is_active ? '' : ' (Inactive)'),
                'value' => $category->id,
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function subcategoryOptions(): array
    {
        return CourseSubcategory::query()
            ->with('category')
            ->orderBy('course_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CourseSubcategory $subcategory): array => [
                'label' => ($subcategory->category?->name ? $subcategory->category->name.' / ' : '').$subcategory->name,
                'value' => $subcategory->id,
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function courseOptions(): array
    {
        return Course::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Course $course): array => ['label' => $course->title, 'value' => $course->id])
            ->all();
    }

    /** @return array<int, array{label: string, value: int, parentValue: int}> */
    private function lessonOptions(): array
    {
        return CourseLesson::query()
            ->orderBy('course_id')
            ->orderBy('order_number')
            ->get(['id', 'course_id', 'title'])
            ->map(fn (CourseLesson $lesson): array => [
                'label' => $lesson->title,
                'value' => $lesson->id,
                'parentValue' => $lesson->course_id,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function seoFields(): array
    {
        return [
            $this->field('seo_title', 'SEO Title', 'text'),
            $this->field('seo_description', 'SEO Description', 'textarea'),
            $this->field('seo_image', 'Social Image URL', 'url'),
            $this->field('canonical_url', 'Canonical URL', 'url'),
            $this->field('schema', 'Schema JSON', 'json'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function field(string $key, string $label, string $type, array $options = [], bool $required = false): array
    {
        return compact('key', 'label', 'type', 'options', 'required');
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function dependentField(
        string $key,
        string $label,
        array $options,
        string $dependsOn,
        string $optionsUrl,
    ): array {
        return $this->field($key, $label, 'select', $options) + compact('dependsOn', 'optionsUrl');
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function bulkAction(string $value, string $label, array $options = []): array
    {
        return compact('value', 'label', 'options');
    }

    /** @return array<int, array{label: string, value: string}> */
    private function activeOptions(): array
    {
        return [
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Inactive', 'value' => 'inactive'],
        ];
    }

    /** @return array<int, array{label: string, value: string}> */
    private function publishStatusOptions(): array
    {
        return $this->enumOptions(PublishStatus::cases());
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{label: string, value: string|int}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (\BackedEnum $case): array => [
            'label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(),
            'value' => $case->value,
        ], $cases);
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return list<string|int>
     */
    private function enumValues(array $cases): array
    {
        return array_map(fn (\BackedEnum $case): string|int => $case->value, $cases);
    }

    private function enumValue(mixed $value): string
    {
        return (string) ($value instanceof \BackedEnum ? $value->value : $value);
    }

    private function syncPublishedAt(Model $record): void
    {
        $status = $this->enumValue($record->getAttribute('status'));
        $record->setAttribute(
            'published_at',
            $status === PublishStatus::Published->value
                ? ($record->getAttribute('published_at') ?: now())
                : null,
        );
    }
}
