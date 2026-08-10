<?php

namespace Modules\Admin\Actions;

use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAdminResourceAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  Closure(): Model  $persist
     */
    public function execute(Request $request, string $resource, Model $record, Closure $persist): Model
    {
        return DB::transaction(function () use ($request, $resource, $record, $persist): Model {
            $before = $record->toArray();
            $updated = $persist();

            $this->auditLogger->log(
                $request,
                "admin.{$resource}.updated",
                $updated,
                $before,
                $updated->fresh()?->toArray(),
            );

            return $updated;
        });
    }
}
