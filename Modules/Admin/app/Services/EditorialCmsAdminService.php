<?php

namespace Modules\Admin\Services;

use App\Enums\PublishStatus;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogFaq;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\ContentBlock;
use App\Models\CreatorAgreementAcceptance;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\MediaUsage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\Security\ContentSanitizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EditorialCmsAdminService
{
    /** @var list<string> */
    public const RESOURCES = [
        'blog_categories',
        'blog_tags',
        'blog_faqs',
        'blog_comments',
        'content_blocks',
        'menu_items',
        'media_folders',
        'media_usages',
        'creator_agreements',
    ];

    /** @var list<string> */
    public const REORDERABLE_RESOURCES = ['blog_faqs', 'content_blocks'];

    /** @var list<string> */
    private const READ_ONLY_RESOURCES = ['media_usages', 'creator_agreements'];

    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function readOnly(string $resource): bool
    {
        return in_array($resource, self::READ_ONLY_RESOURCES, true);
    }

    public function reorderable(string $resource): bool
    {
        return in_array($resource, self::REORDERABLE_RESOURCES, true);
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'blog_categories' => ['name', 'slug', 'status', 'posts_count'],
            'blog_tags' => ['name', 'slug', 'posts_count'],
            'blog_faqs' => ['question', 'blog', 'status', 'sort_order'],
            'blog_comments' => ['blog', 'author', 'status', 'parent', 'created_at'],
            'content_blocks' => ['title', 'key', 'page', 'status', 'sort_order'],
            'menu_items' => ['title', 'menu', 'parent', 'type', 'status', 'sort_order'],
            'media_folders' => ['name', 'parent', 'path', 'folders_count', 'assets_count'],
            'media_usages' => ['asset', 'used_by', 'collection', 'created_at'],
            'creator_agreements' => ['creator', 'email', 'terms_version', 'accepted_at', 'ip_address'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /** @return array<int, array<string, mixed>> */
    public function fields(string $resource): array
    {
        return match ($resource) {
            'blog_categories' => [
                $this->field('name', 'Name', 'text', required: true),
                $this->field('description', 'Description', 'textarea'),
                $this->field('is_active', 'Active', 'checkbox'),
                ...$this->seoFields(),
            ],
            'blog_tags' => [$this->field('name', 'Name', 'text', required: true)],
            'blog_faqs' => [
                $this->field('blog_post_id', 'Blog', 'select', $this->blogOptions(), true),
                $this->field('question', 'Question', 'text', required: true),
                $this->field('answer', 'Answer', 'richtext', required: true),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('status', 'Status', 'select', $this->publishStatusOptions(), true),
            ],
            'blog_comments' => [
                $this->field('blog_post_id', 'Blog', 'select', $this->blogOptions(), true),
                $this->field('user_id', 'Author', 'select', $this->userOptions()),
                $this->field('parent_id', 'Parent Comment', 'select', $this->commentOptions()),
                $this->field('body', 'Comment', 'textarea', required: true),
                $this->field('status', 'Status', 'select', $this->publishStatusOptions(), true),
            ],
            'content_blocks' => [
                $this->field('page_id', 'CMS Page', 'select', $this->pageOptions()),
                $this->field('key', 'Block Key', 'text', required: true),
                $this->field('title', 'Title', 'text'),
                $this->field('body', 'Content', 'richtext'),
                $this->field('settings_content', 'Settings JSON', 'json'),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
            ],
            'menu_items' => [
                $this->field('menu_id', 'Menu', 'select', $this->menuOptions(), true),
                $this->field('parent_id', 'Parent Item', 'select', $this->menuItemOptions()),
                $this->field('page_id', 'CMS Page', 'select', $this->pageOptions()),
                $this->field('title', 'Title', 'text', required: true),
                $this->field('type', 'Link Type', 'select', [
                    ['label' => 'URL', 'value' => 'url'],
                    ['label' => 'CMS Page', 'value' => 'page'],
                    ['label' => 'Named Route', 'value' => 'route'],
                ], true),
                $this->field('url', 'URL', 'text'),
                $this->field('route_name', 'Route Name', 'text'),
                $this->field('sort_order', 'Sort Order', 'number'),
                $this->field('is_active', 'Active', 'checkbox'),
            ],
            'media_folders' => [
                $this->field('parent_id', 'Parent Folder', 'select', $this->folderOptions()),
                $this->field('name', 'Name', 'text', required: true),
            ],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];

        if (in_array($resource, ['blog_categories', 'content_blocks', 'menu_items'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->activeOptions());
        }

        if (in_array($resource, ['blog_faqs', 'blog_comments'], true)) {
            $filters[] = $this->field('status', 'Status', 'select', $this->publishStatusOptions());
        }

        if (in_array($resource, ['blog_faqs', 'blog_comments'], true)) {
            $filters[] = $this->field('category', 'Blog', 'select', $this->blogOptions());
        }

        if ($resource === 'content_blocks') {
            $filters[] = $this->field('category', 'CMS Page', 'select', $this->pageOptions());
        }

        if ($resource === 'menu_items') {
            $filters[] = $this->field('category', 'Menu', 'select', $this->menuOptions());
        }

        if ($resource === 'media_folders') {
            $filters[] = $this->field('category', 'Parent Folder', 'select', $this->folderOptions());
        }

        if ($resource === 'media_usages') {
            $filters[] = $this->field('category', 'Media Asset', 'select', $this->mediaOptions());
        }

        if ($resource === 'creator_agreements') {
            $filters[] = $this->field('category', 'Creator', 'select', $this->userOptions());
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkActions(string $resource): array
    {
        if ($this->readOnly($resource)) {
            return [];
        }

        return match ($resource) {
            'blog_categories', 'content_blocks', 'menu_items' => [
                $this->bulkAction('active', 'Set Active State', $this->activeOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            'blog_faqs', 'blog_comments' => [
                $this->bulkAction('status', 'Set Status', $this->publishStatusOptions()),
                $this->bulkAction('delete', 'Delete'),
            ],
            default => [$this->bulkAction('delete', 'Delete')],
        };
    }

    /** @return array<string, int> */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'blog_categories' => ['total' => BlogCategory::query()->count(), 'active' => BlogCategory::query()->where('is_active', true)->count()],
            'blog_tags' => ['total' => BlogTag::query()->count(), 'in_use' => BlogTag::query()->has('posts')->count()],
            'blog_faqs' => ['total' => BlogFaq::query()->count(), 'published' => BlogFaq::query()->where('status', PublishStatus::Published->value)->count()],
            'blog_comments' => ['pending' => BlogComment::query()->where('status', PublishStatus::Pending->value)->count(), 'published' => BlogComment::query()->where('status', PublishStatus::Published->value)->count()],
            'content_blocks' => ['total' => ContentBlock::query()->count(), 'active' => ContentBlock::query()->where('is_active', true)->count()],
            'menu_items' => ['total' => MenuItem::query()->count(), 'active' => MenuItem::query()->where('is_active', true)->count()],
            'media_folders' => ['folders' => MediaFolder::query()->count(), 'assets' => MediaAsset::query()->count()],
            'media_usages' => ['references' => MediaUsage::query()->count(), 'assets_in_use' => MediaUsage::query()->distinct()->count('media_asset_id')],
            'creator_agreements' => ['acceptances' => CreatorAgreementAcceptance::query()->count(), 'creators' => CreatorAgreementAcceptance::query()->distinct()->count('user_id')],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'blog_categories' => $this->categoryRow($record),
            'blog_tags' => $this->tagRow($record),
            'blog_faqs' => $this->faqRow($record),
            'blog_comments' => $this->commentRow($record),
            'content_blocks' => $this->blockRow($record),
            'menu_items' => $this->menuItemRow($record),
            'media_folders' => $this->folderRow($record),
            'media_usages' => $this->usageRow($record),
            'creator_agreements' => $this->agreementRow($record),
            default => [],
        };
    }

    public function persist(Request $request, string $resource, ?Model $record = null): Model
    {
        abort_if($this->readOnly($resource), 405, 'This history resource is read-only.');

        return match ($resource) {
            'blog_categories' => $this->persistCategory($request, $record),
            'blog_tags' => $this->persistTag($request, $record),
            'blog_faqs' => $this->persistFaq($request, $record),
            'blog_comments' => $this->persistComment($request, $record),
            'content_blocks' => $this->persistBlock($request, $record),
            'menu_items' => $this->persistMenuItem($request, $record),
            'media_folders' => $this->persistFolder($request, $record),
            default => throw ValidationException::withMessages(['resource' => 'Unsupported editorial resource.']),
        };
    }

    public function applyBulkMutation(string $resource, Model $record, string $action, ?string $value): bool
    {
        if ($action === 'active' && in_array($resource, ['blog_categories', 'content_blocks', 'menu_items'], true)) {
            $record->setAttribute('is_active', $value === 'active');
            $record->save();

            return true;
        }

        if ($action === 'status' && in_array($resource, ['blog_faqs', 'blog_comments'], true)) {
            $record->setAttribute('status', $value);

            if ($record instanceof BlogFaq) {
                $this->syncPublishedAt($record);
            }

            $record->save();

            return true;
        }

        return false;
    }

    public function guardDeletion(string $resource, Model $record): void
    {
        abort_if($this->readOnly($resource), 405, 'History records cannot be deleted here.');

        if ($record instanceof BlogCategory && $record->posts()->exists()) {
            throw ValidationException::withMessages(['delete' => 'Reassign this category’s blog posts before deleting it.']);
        }

        if ($record instanceof MenuItem && $record->children()->exists()) {
            throw ValidationException::withMessages(['delete' => 'Move or delete child menu items first.']);
        }

        if ($record instanceof MediaFolder && ($record->children()->exists() || $record->assets()->exists())) {
            throw ValidationException::withMessages(['delete' => 'Move this folder’s assets and child folders before deleting it.']);
        }
    }

    /** @param array<int, array{id: int, sort_order: int}> $items */
    public function reorder(string $resource, array $items): void
    {
        abort_unless($this->reorderable($resource), 404);
        $class = $resource === 'blog_faqs' ? BlogFaq::class : ContentBlock::class;

        foreach ($items as $item) {
            $class::query()->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }
    }

    private function persistCategory(Request $request, ?Model $record): BlogCategory
    {
        $category = $record instanceof BlogCategory ? $record : new BlogCategory;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_image' => ['nullable', 'url', 'max:2048'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'schema' => ['nullable', 'json'],
        ]);
        $data['schema'] = filled($data['schema'] ?? null) ? json_decode((string) $data['schema'], true, 512, JSON_THROW_ON_ERROR) : null;
        $category->fill($data)->save();

        return $category;
    }

    private function persistTag(Request $request, ?Model $record): BlogTag
    {
        $tag = $record instanceof BlogTag ? $record : new BlogTag;
        $tag->fill($request->validate(['name' => ['required', 'string', 'max:255']]))->save();

        return $tag;
    }

    private function persistFaq(Request $request, ?Model $record): BlogFaq
    {
        $faq = $record instanceof BlogFaq ? $record : new BlogFaq;
        $data = $request->validate([
            'blog_post_id' => ['required', 'integer', 'exists:blog_posts,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in($this->enumValues(PublishStatus::cases()))],
        ]);
        $data['answer'] = $this->sanitizer->richText($data['answer']);
        $faq->fill($data);
        $this->syncPublishedAt($faq);
        $faq->save();

        return $faq;
    }

    private function persistComment(Request $request, ?Model $record): BlogComment
    {
        $comment = $record instanceof BlogComment ? $record : new BlogComment;
        $data = $request->validate([
            'blog_post_id' => ['required', 'integer', 'exists:blog_posts,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:blog_comments,id'],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['required', Rule::in($this->enumValues(PublishStatus::cases()))],
        ]);

        if (filled($data['parent_id'] ?? null)) {
            $parent = BlogComment::query()->whereKey((int) $data['parent_id'])->firstOrFail();
            if ((int) $parent->blog_post_id !== (int) $data['blog_post_id'] || $parent->is($comment)) {
                throw ValidationException::withMessages(['parent_id' => 'The parent comment must belong to the selected blog.']);
            }
        }

        $data['body'] = $this->sanitizer->plainText($data['body']);
        $comment->fill($data)->save();

        return $comment;
    }

    private function persistBlock(Request $request, ?Model $record): ContentBlock
    {
        $block = $record instanceof ContentBlock ? $record : new ContentBlock;
        $data = $request->validate([
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'key' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'settings_content' => ['nullable', 'json'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $data['key'] = Str::slug($data['key'], '_');
        $data['body'] = $this->sanitizer->richText($data['body'] ?? '');
        $data['settings'] = filled($data['settings_content'] ?? null)
            ? json_decode((string) $data['settings_content'], true, 512, JSON_THROW_ON_ERROR)
            : null;
        unset($data['settings_content']);
        $block->fill($data)->save();

        return $block;
    }

    private function persistMenuItem(Request $request, ?Model $record): MenuItem
    {
        $item = $record instanceof MenuItem ? $record : new MenuItem;
        $data = $request->validate([
            'menu_id' => ['required', 'integer', 'exists:menus,id'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['url', 'page', 'route'])],
            'url' => ['nullable', 'string', 'max:2048'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if ($data['type'] === 'page' && blank($data['page_id'] ?? null)) {
            throw ValidationException::withMessages(['page_id' => 'Select a CMS page for a page link.']);
        }
        if ($data['type'] === 'url' && blank($data['url'] ?? null)) {
            throw ValidationException::withMessages(['url' => 'Enter a URL for a URL link.']);
        }
        if ($data['type'] === 'route' && blank($data['route_name'] ?? null)) {
            throw ValidationException::withMessages(['route_name' => 'Enter a route name for a route link.']);
        }

        if (filled($data['parent_id'] ?? null)) {
            $parent = MenuItem::query()->whereKey((int) $data['parent_id'])->firstOrFail();
            if ((int) $parent->menu_id !== (int) $data['menu_id'] || $parent->is($item) || $this->isDescendant($parent, $item)) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a parent in the same menu that is not a descendant of this item.']);
            }
        }

        $item->fill($data)->save();

        return $item;
    }

    private function persistFolder(Request $request, ?Model $record): MediaFolder
    {
        $folder = $record instanceof MediaFolder ? $record : new MediaFolder;
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (filled($data['parent_id'] ?? null)) {
            $parent = MediaFolder::query()->whereKey((int) $data['parent_id'])->firstOrFail();
            if ($parent->is($folder) || $this->isFolderDescendant($parent, $folder)) {
                throw ValidationException::withMessages(['parent_id' => 'A folder cannot be moved inside itself or one of its descendants.']);
            }
        }

        $folder->fill($data)->save();
        $folder->forceFill(['path' => $this->folderPath($folder)])->save();
        $this->refreshDescendantFolderPaths($folder);

        return $folder;
    }

    /** @return array<string, mixed> */
    private function categoryRow(Model $record): array
    {
        /** @var BlogCategory $record */
        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'posts_count' => $record->posts_count ?? $record->posts()->count(),
            'form' => Arr::only($record->toArray(), ['name', 'description', 'is_active', 'seo_title', 'seo_description', 'seo_image', 'canonical_url']) + [
                'schema' => $record->getAttribute('schema') ? json_encode($record->getAttribute('schema'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function tagRow(Model $record): array
    {
        /** @var BlogTag $record */
        return ['id' => $record->id, 'name' => $record->name, 'slug' => $record->slug, 'posts_count' => $record->posts_count ?? $record->posts()->count(), 'form' => ['name' => $record->name]];
    }

    /** @return array<string, mixed> */
    private function faqRow(Model $record): array
    {
        /** @var BlogFaq $record */
        return [
            'id' => $record->id,
            'question' => $record->question,
            'blog' => $record->post?->title,
            'status' => $this->enumValue($record->status),
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), ['blog_post_id', 'question', 'answer', 'sort_order']) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /** @return array<string, mixed> */
    private function commentRow(Model $record): array
    {
        /** @var BlogComment $record */
        return [
            'id' => $record->id,
            'blog' => $record->post?->title,
            'author' => $record->user?->email ?: 'Guest',
            'status' => $this->enumValue($record->status),
            'parent' => $record->parent?->body ? Str::limit($record->parent->body, 50) : null,
            'created_at' => $record->created_at?->toDateString(),
            'form' => Arr::only($record->toArray(), ['blog_post_id', 'user_id', 'parent_id', 'body']) + ['status' => $this->enumValue($record->status)],
        ];
    }

    /** @return array<string, mixed> */
    private function blockRow(Model $record): array
    {
        /** @var ContentBlock $record */
        return [
            'id' => $record->id,
            'title' => $record->title ?: Str::headline($record->key),
            'key' => $record->key,
            'page' => $record->page?->title ?: 'Global',
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), ['page_id', 'key', 'title', 'body', 'sort_order', 'is_active']) + [
                'settings_content' => $record->settings ? json_encode($record->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuItemRow(Model $record): array
    {
        /** @var MenuItem $record */
        return [
            'id' => $record->id,
            'title' => $record->title,
            'menu' => $record->menu?->name,
            'parent' => $record->parent?->title,
            'type' => Str::headline($record->type),
            'status' => $record->is_active ? 'Active' : 'Inactive',
            'sort_order' => $record->sort_order,
            'form' => Arr::only($record->toArray(), ['menu_id', 'parent_id', 'page_id', 'title', 'type', 'url', 'route_name', 'sort_order', 'is_active']),
        ];
    }

    /** @return array<string, mixed> */
    private function folderRow(Model $record): array
    {
        /** @var MediaFolder $record */
        return [
            'id' => $record->id,
            'name' => $record->name,
            'parent' => $record->parent?->name,
            'path' => $record->path,
            'folders_count' => $record->children_count ?? $record->children()->count(),
            'assets_count' => $record->assets_count ?? $record->assets()->count(),
            'form' => Arr::only($record->toArray(), ['parent_id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function usageRow(Model $record): array
    {
        /** @var MediaUsage $record */
        return [
            'id' => $record->id,
            'asset' => $record->asset?->title ?: basename((string) $record->asset?->path),
            'used_by' => class_basename((string) $record->mediable_type).' #'.$record->mediable_id,
            'collection' => $record->collection,
            'created_at' => $record->created_at?->toDateString(),
            'form' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function agreementRow(Model $record): array
    {
        /** @var CreatorAgreementAcceptance $record */
        return [
            'id' => $record->id,
            'creator' => $record->user?->name,
            'email' => $record->user?->email,
            'terms_version' => $record->terms_version,
            'accepted_at' => $this->dateTimeString($record->getAttribute('accepted_at')),
            'ip_address' => $record->ip_address,
            'form' => [],
            'review_items' => [
                ['label' => 'User Agent', 'value' => $record->user_agent],
                ['label' => 'IP Address', 'value' => $record->ip_address],
            ],
        ];
    }

    private function isDescendant(MenuItem $candidate, MenuItem $item): bool
    {
        if (! $item->exists) {
            return false;
        }

        $current = $candidate;
        while ($current->parent_id) {
            if ((int) $current->parent_id === (int) $item->id) {
                return true;
            }
            $current = $current->parent()->first();
            if (! $current) {
                break;
            }
        }

        return false;
    }

    private function isFolderDescendant(MediaFolder $candidate, MediaFolder $folder): bool
    {
        if (! $folder->exists) {
            return false;
        }

        $current = $candidate;
        while ($current->parent_id) {
            if ((int) $current->parent_id === (int) $folder->id) {
                return true;
            }
            $current = $current->parent()->first();
            if (! $current) {
                break;
            }
        }

        return false;
    }

    private function folderPath(MediaFolder $folder): string
    {
        $parentPath = $folder->parent?->path;

        return trim(($parentPath ? $parentPath.'/' : '').$folder->slug, '/');
    }

    private function refreshDescendantFolderPaths(MediaFolder $folder): void
    {
        $folder->children()->each(function (MediaFolder $child): void {
            $child->forceFill(['path' => $this->folderPath($child)])->save();
            $this->refreshDescendantFolderPaths($child);
        });
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
    private function bulkAction(string $value, string $label, array $options = []): array
    {
        return compact('value', 'label', 'options');
    }

    /** @return array<int, array{label: string, value: string}> */
    private function activeOptions(): array
    {
        return [['label' => 'Active', 'value' => 'active'], ['label' => 'Inactive', 'value' => 'inactive']];
    }

    /** @return array<int, array{label: string, value: string}> */
    private function publishStatusOptions(): array
    {
        return array_map(fn (PublishStatus $status): array => ['label' => Str::headline($status->value), 'value' => $status->value], PublishStatus::cases());
    }

    /** @return array<int, array{label: string, value: int}> */
    private function blogOptions(): array
    {
        return BlogPost::query()->orderBy('title')->get(['id', 'title'])->map(fn (BlogPost $post): array => ['label' => $post->title, 'value' => $post->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function pageOptions(): array
    {
        return Page::query()->orderBy('title')->get(['id', 'title'])->map(fn (Page $page): array => ['label' => $page->title, 'value' => $page->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function userOptions(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $user): array => ['label' => $user->name.' ('.$user->email.')', 'value' => $user->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function commentOptions(): array
    {
        return BlogComment::query()->latest()->limit(250)->get(['id', 'body'])->map(fn (BlogComment $comment): array => ['label' => '#'.$comment->id.' '.Str::limit($comment->body, 60), 'value' => $comment->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function menuOptions(): array
    {
        return Menu::query()->orderBy('name')->get(['id', 'name'])->map(fn (Menu $menu): array => ['label' => $menu->name, 'value' => $menu->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function menuItemOptions(): array
    {
        return MenuItem::query()->with('menu')->orderBy('menu_id')->orderBy('sort_order')->get()->map(fn (MenuItem $item): array => ['label' => ($item->menu?->name ? $item->menu->name.' / ' : '').$item->title, 'value' => $item->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function folderOptions(): array
    {
        return MediaFolder::query()->orderBy('path')->get(['id', 'name', 'path'])->map(fn (MediaFolder $folder): array => ['label' => $folder->path ?: $folder->name, 'value' => $folder->id])->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function mediaOptions(): array
    {
        return MediaAsset::query()->orderBy('title')->get(['id', 'title', 'path'])->map(fn (MediaAsset $asset): array => ['label' => $asset->title ?: basename($asset->path), 'value' => $asset->id])->all();
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return list<string|int>
     */
    private function enumValues(array $cases): array
    {
        return array_values(array_map(fn (\BackedEnum $case): string|int => $case->value, $cases));
    }

    private function enumValue(mixed $value): string
    {
        return (string) ($value instanceof \BackedEnum ? $value->value : $value);
    }

    private function dateTimeString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface
            ? $value->toDateTimeString()
            : (is_string($value) ? $value : null);
    }

    private function syncPublishedAt(BlogFaq $faq): void
    {
        $faq->published_at = $this->enumValue($faq->status) === PublishStatus::Published->value
            ? ($faq->published_at ?: now())
            : null;
    }
}
