<?php

namespace Modules\Admin\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AdminMenuBuilderService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return array<string, mixed> */
    public function payload(Menu $menu): array
    {
        $items = $menu->items()
            ->with(['parent', 'page'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'location' => $menu->location,
                'is_active' => $menu->is_active,
            ],
            'items' => $items->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'menu_id' => $item->menu_id,
                'parent_id' => $item->parent_id,
                'page_id' => $item->page_id,
                'title' => $item->title,
                'type' => $item->type,
                'url' => $item->url,
                'route_name' => $item->route_name,
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
                'page_title' => $item->page?->title,
            ])->values()->all(),
            'pages' => Page::query()->orderBy('title')->get(['id', 'title'])->map(fn (Page $page): array => [
                'label' => $page->title,
                'value' => $page->id,
            ])->all(),
            'urls' => [
                'index' => route('admin.menus.index', absolute: false),
                'store' => route('admin.menus.items.store', absolute: false),
                'update_base' => '/admin/menus/items',
                'delete_base' => '/admin/menus/items',
                'reorder' => route('admin.menus.builder.reorder', $menu, false),
            ],
        ];
    }

    /** @param list<array{id: int, parent_id: int|null, sort_order: int}> $items */
    public function reorder(Request $request, Menu $menu, array $items): void
    {
        DB::transaction(function () use ($request, $menu, $items): void {
            $records = $menu->items()->whereKey(array_column($items, 'id'))->lockForUpdate()->get()->keyBy('id');
            if ($records->count() !== count($items) || $records->count() !== $menu->items()->count()) {
                throw ValidationException::withMessages(['items' => 'The menu changed while you were editing. Refresh and try again.']);
            }

            $parents = [];
            foreach ($items as $item) {
                $parentId = $item['parent_id'] ?? null;
                if ($parentId !== null && ! $records->has($parentId)) {
                    throw ValidationException::withMessages(['items' => 'Every parent must belong to this menu.']);
                }
                if ($parentId !== null && (int) $parentId === (int) $item['id']) {
                    throw ValidationException::withMessages(['items' => 'A menu item cannot be its own parent.']);
                }
                $parents[(int) $item['id']] = $parentId === null ? null : (int) $parentId;
            }

            foreach (array_keys($parents) as $id) {
                $visited = [];
                $cursor = $id;
                while (($cursor = $parents[$cursor] ?? null) !== null) {
                    if (isset($visited[$cursor]) || $cursor === $id) {
                        throw ValidationException::withMessages(['items' => 'The requested hierarchy contains a circular parent relationship.']);
                    }
                    $visited[$cursor] = true;
                }
            }

            foreach ($items as $item) {
                /** @var MenuItem $record */
                $record = $records->get($item['id']);
                $before = $record->toArray();
                $record->forceFill([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ])->save();
                $this->auditLogger->log($request, 'admin.menu_items.reordered', $record, $before, $record->fresh()?->toArray());
            }
        });
    }
}
