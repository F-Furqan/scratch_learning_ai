<?php

namespace Modules\Admin\Actions;

use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class CreateAdminResourceAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  Closure(): Model  $persist
     */
    public function execute(Request $request, string $resource, Closure $persist): Model
    {
        return DB::transaction(function () use ($request, $resource, $persist): Model {
            $record = $persist();

            $this->auditLogger->log(
                $request,
                "admin.{$resource}.created",
                $record,
                null,
                $record->fresh()?->toArray(),
            );

            return $record;
        });
    }
}
