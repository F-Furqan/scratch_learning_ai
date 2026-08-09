<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\CourseLesson;
use App\Models\User;
use App\Policies\Concerns\ComparesBackedEnums;

class CourseLessonPolicy
{
    use ComparesBackedEnums;

    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function view(User $user, CourseLesson $lesson): bool
    {
        return $lesson->isPublished()
            || $this->ownsCourse($user, $lesson)
            || $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function update(User $user, CourseLesson $lesson): bool
    {
        return $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value)
            || ($this->ownsEditableCreatorCourse($user, $lesson));
    }

    public function delete(User $user, CourseLesson $lesson): bool
    {
        return $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function submit(User $user, CourseLesson $lesson): bool
    {
        return $this->update($user, $lesson)
            && $this->enumIn($lesson->getAttribute('status'), [PublishStatus::Draft, PublishStatus::Rejected]);
    }

    public function publish(User $user, CourseLesson $lesson): bool
    {
        return $user->can(PermissionName::ManageLessons->value)
            || $user->can(PermissionName::ManageCourses->value);
    }

    public function reject(User $user, CourseLesson $lesson): bool
    {
        return $this->publish($user, $lesson)
            && $this->enumEquals($lesson->getAttribute('status'), PublishStatus::Pending);
    }

    public function archive(User $user, CourseLesson $lesson): bool
    {
        return $this->publish($user, $lesson);
    }

    private function ownsCourse(User $user, CourseLesson $lesson): bool
    {
        return $lesson->course?->created_by === $user->id;
    }

    private function ownsEditableCreatorCourse(User $user, CourseLesson $lesson): bool
    {
        return $this->ownsCourse($user, $lesson)
            && $user->hasAcceptedCreatorAgreement()
            && ! $this->enumEquals($lesson->getAttribute('status'), PublishStatus::Published);
    }
}
