<?php

namespace Modules\Admin\Actions;

use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class DeleteAdminResourceAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  Closure(Model): void  $delete
     */
    public function execute(Request $request, string $resource, Model $record, Closure $delete): void
    {
        DB::transaction(function () use ($request, $resource, $record, $delete): void {
            $before = $record->toArray();
            $delete($record);

            $this->auditLogger->log(
                $request,
                "admin.{$resource}.deleted",
                $record,
                $before,
                $record->fresh()?->toArray(),
            );
        });
    }
}
