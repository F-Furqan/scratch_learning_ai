<?php

namespace App\Policies;

use App\Enums\MediaVisibility;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\MediaAsset;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class MediaAssetPolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageMedia->value);
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->enumEquals($asset->getAttribute('visibility'), MediaVisibility::Public)
            || $asset->uploaded_by === $user->id
            || $user->can(PermissionName::ManageMedia->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageMedia->value);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $asset->uploaded_by === $user->id
            || $user->can(PermissionName::ManageMedia->value);
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->can(PermissionName::ManageMedia->value);
    }
}
