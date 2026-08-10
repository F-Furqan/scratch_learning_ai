<?php

namespace Modules\Admin\Actions;

use Closure;
use Illuminate\Support\Facades\DB;

final class BulkAdminResourceAction
{
    /**
     * @param  Closure(): void  $operation
     */
    public function execute(Closure $operation): void
    {
        DB::transaction($operation);
    }
}
