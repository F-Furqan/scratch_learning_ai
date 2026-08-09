<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\Page;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class PagePolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageCms->value);
    }

    public function view(User $user, Page $page): bool
    {
        return $page->isPublished()
            || $page->author_id === $user->id
            || $user->can(PermissionName::ManageCms->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageCms->value);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(PermissionName::ManageCms->value)
            || ($page->author_id === $user->id && ! $this->enumEquals($page->getAttribute('status'), PublishStatus::Published));
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can(PermissionName::ManageCms->value);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can(PermissionName::ManageCms->value);
    }

    public function archive(User $user, Page $page): bool
    {
        return $user->can(PermissionName::ManageCms->value);
    }
}
