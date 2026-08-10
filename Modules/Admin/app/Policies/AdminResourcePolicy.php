<?php

namespace Modules\Admin\Policies;

use App\Enums\RoleName;
use App\Models\User;
use Modules\Admin\Data\AdminResourceDefinition;

final class AdminResourcePolicy
{
    public function view(User $user, AdminResourceDefinition $definition): bool
    {
        return $this->allows($user, $definition->permissionsFor('view'));
    }

    public function manage(User $user, AdminResourceDefinition $definition): bool
    {
        return $this->allows($user, [
            ...$definition->legacyPermissions,
            $definition->managePermission(),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function allows(User $user, array $permissions): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return collect($permissions)->contains(
            fn (string $permission): bool => $user->can($permission),
        );
    }
}
