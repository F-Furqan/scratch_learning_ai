<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class CoursePolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageCourses->value);
    }

    public function view(User $user, Course $course): bool
    {
        return $course->isPublished()
            || $this->owns($user, $course)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageCourses->value)
            || $this->canSubmitCreatorContent($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ManageCourses->value)
            || ($this->ownsEditableCreatorContent($user, $course));
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ManageCourses->value);
    }

    public function requestDelete(User $user, Course $course): bool
    {
        return $this->owns($user, $course)
            && $user->hasAcceptedCreatorAgreement()
            && ! $this->enumEquals($course->getAttribute('status'), PublishStatus::Trashed);
    }

    public function submit(User $user, Course $course): bool
    {
        return $this->update($user, $course)
            && $this->enumIn($course->getAttribute('status'), [
                PublishStatus::Draft,
                PublishStatus::Rejected,
            ]);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ManageCourses->value);
    }

    public function requestRevision(User $user, Course $course): bool
    {
        return $this->owns($user, $course)
            && $user->hasAcceptedCreatorAgreement()
            && $this->enumEquals($course->getAttribute('status'), PublishStatus::Published);
    }

    public function reject(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ManageCourses->value)
            && $this->enumEquals($course->getAttribute('status'), PublishStatus::Pending);
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->can(PermissionName::ManageCourses->value);
    }

    private function owns(User $user, Course $course): bool
    {
        return $course->created_by === $user->id;
    }

    private function ownsEditableCreatorContent(User $user, Course $course): bool
    {
        return $this->owns($user, $course)
            && $user->hasAcceptedCreatorAgreement()
            && $this->enumIn($course->getAttribute('status'), [
                PublishStatus::Draft,
                PublishStatus::Rejected,
            ]);
    }

    private function canSubmitCreatorContent(User $user): bool
    {
        return $user->isApprovedBlogger()
            && $user->hasAcceptedCreatorAgreement();
    }
}
