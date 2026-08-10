<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Admin\AdminOperationController;
use Modules\Admin\Enums\AdminDomain;

final class LearningAdminController extends AdminOperationController
{
    protected function domain(): AdminDomain
    {
        return AdminDomain::Learning;
    }
}
