<?php

namespace Modules\Admin\Actions;

use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Services\CourseCatalogAdminService;

final readonly class ReorderCatalogResourcesAction
{
    public function __construct(
        private CourseCatalogAdminService $catalog,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{id: int, sort_order: int}>  $items
     */
    public function execute(Request $request, string $resource, array $items): void
    {
        DB::transaction(function () use ($request, $resource, $items): void {
            $modelClass = $this->catalog->modelClass($resource);
            $records = $modelClass::query()
                ->whereKey(array_column($items, 'id'))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($model) => $model->getKey());

            abort_unless($records->count() === count($items), 422, 'One or more catalog records no longer exist.');

            foreach ($items as $item) {
                $record = $records->get($item['id']);
                $before = $record->toArray();
                $record->setAttribute('sort_order', $item['sort_order']);
                $record->save();

                $this->auditLogger->log(
                    $request,
                    "admin.{$resource}.reordered",
                    $record,
                    $before,
                    $record->fresh()?->toArray(),
                );
            }
        });
    }
}
