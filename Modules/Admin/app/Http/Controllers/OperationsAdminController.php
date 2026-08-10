<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Admin\AdminOperationController;
use Modules\Admin\Enums\AdminDomain;

final class OperationsAdminController extends AdminOperationController
{
    protected function domain(): AdminDomain
    {
        return AdminDomain::Operations;
    }
}
