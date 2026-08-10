<?php

namespace Modules\Admin\Actions;

use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Services\EditorialCmsAdminService;

final readonly class ReorderEditorialResourcesAction
{
    public function __construct(
        private EditorialCmsAdminService $editorial,
        private AuditLogger $auditLogger,
    ) {}

    /** @param list<array{id: int, sort_order: int}> $items */
    public function execute(Request $request, string $resource, array $items): void
    {
        DB::transaction(function () use ($request, $resource, $items): void {
            $this->editorial->reorder($resource, $items);
            $this->auditLogger->log($request, "admin.{$resource}.reordered", metadata: ['items' => $items]);
        });
    }
}
