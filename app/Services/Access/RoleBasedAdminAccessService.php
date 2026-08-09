<?php

namespace App\Services\Access;

use App\Contracts\Access\AdminAccessService;
use App\Enums\RoleName;
use App\Models\User;

class RoleBasedAdminAccessService implements AdminAccessService
{
    public function canAccess(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(RoleName::SubAdmin->value)
            && $user->getAllPermissions()->isNotEmpty();
    }
}
