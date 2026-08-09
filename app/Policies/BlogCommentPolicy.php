<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlogComment;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class BlogCommentPolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageComments->value);
    }

    public function view(User $user, BlogComment $comment): bool
    {
        return $this->enumEquals($comment->getAttribute('status'), PublishStatus::Published)
            || $comment->user_id === $user->id
            || $user->can(PermissionName::ManageComments->value);
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, BlogComment $comment): bool
    {
        return $comment->user_id === $user->id
            || $user->can(PermissionName::ManageComments->value);
    }

    public function delete(User $user, BlogComment $comment): bool
    {
        return $comment->user_id === $user->id
            || $user->can(PermissionName::ManageComments->value);
    }

    public function moderate(User $user, BlogComment $comment): bool
    {
        return $user->can(PermissionName::ManageComments->value);
    }
}
