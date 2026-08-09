<?php

namespace App\Services\Navigation;

use App\Contracts\Access\AdminAccessService;
use App\Contracts\Navigation\DashboardDestinationResolver;
use App\Enums\RoleName;
use App\Models\User;

class RoleBasedDashboardDestinationResolver implements DashboardDestinationResolver
{
    public function __construct(
        private readonly AdminAccessService $adminAccess,
    ) {}

    public function pathFor(User $user): string
    {
        if ($this->adminAccess->canAccess($user)) {
            return route('admin.dashboard', absolute: false);
        }

        if ($user->hasRole(RoleName::Blogger->value)) {
            return route('creator.dashboard', absolute: false);
        }

        return route('student.dashboard', absolute: false);
    }
}
