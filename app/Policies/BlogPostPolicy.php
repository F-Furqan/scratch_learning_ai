<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlogPost;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class BlogPostPolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageBlogs->value);
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $post->isPublished()
            || $this->owns($user, $post)
            || $user->can(PermissionName::ManageBlogs->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageBlogs->value)
            || $this->canSubmitCreatorContent($user);
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $user->can(PermissionName::ManageBlogs->value)
            || ($this->ownsEditableCreatorContent($user, $post));
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->can(PermissionName::ManageBlogs->value)
            || ($this->ownsEditableCreatorContent($user, $post));
    }

    public function requestDelete(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post)
            && $user->hasAcceptedCreatorAgreement()
            && ! $this->enumEquals($post->getAttribute('status'), PublishStatus::Trashed);
    }

    public function submit(User $user, BlogPost $post): bool
    {
        return $this->update($user, $post)
            && $this->enumIn($post->getAttribute('status'), [
                PublishStatus::Draft,
                PublishStatus::ChangesRequested,
                PublishStatus::Rejected,
            ]);
    }

    public function requestRevision(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post)
            && $user->hasAcceptedCreatorAgreement()
            && $this->enumEquals($post->getAttribute('status'), PublishStatus::Published);
    }

    public function publish(User $user, BlogPost $post): bool
    {
        return $user->can(PermissionName::ManageBlogs->value);
    }

    public function reject(User $user, BlogPost $post): bool
    {
        return $user->can(PermissionName::ManageBlogs->value)
            && $this->enumIn($post->getAttribute('status'), [
                PublishStatus::Submitted,
                PublishStatus::Approved,
                PublishStatus::ChangesRequested,
            ]);
    }

    public function archive(User $user, BlogPost $post): bool
    {
        return $user->can(PermissionName::ManageBlogs->value);
    }

    private function owns(User $user, BlogPost $post): bool
    {
        return $post->author_id === $user->id;
    }

    private function ownsEditableCreatorContent(User $user, BlogPost $post): bool
    {
        return $this->owns($user, $post)
            && $user->hasAcceptedCreatorAgreement()
            && $this->enumIn($post->getAttribute('status'), [
                PublishStatus::Draft,
                PublishStatus::ChangesRequested,
                PublishStatus::Rejected,
            ]);
    }

    private function canSubmitCreatorContent(User $user): bool
    {
        return $user->isApprovedBlogger()
            && $user->hasAcceptedCreatorAgreement();
    }
}
