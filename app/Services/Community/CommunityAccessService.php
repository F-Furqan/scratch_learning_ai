<?php

namespace App\Services\Community;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\CommunityGroup;
use App\Models\DiscussionForum;
use App\Models\User;
use App\Services\Payments\CourseAccessService;
use BackedEnum;

class CommunityAccessService
{
    public function __construct(
        private readonly CourseAccessService $courseAccess,
    ) {}

    public function canAccessForum(?User $user, DiscussionForum $forum): bool
    {
        if ($this->enumValue($forum->getAttribute('status')) !== CommunityContentStatus::Approved->value) {
            return false;
        }

        $visibility = $this->enumValue($forum->getAttribute('visibility'));

        if ($visibility === CommunityVisibility::Public->value) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($visibility === CommunityVisibility::Members->value) {
            return true;
        }

        return $forum->course ? $this->courseAccess->canAccessCourse($user, $forum->course) : true;
    }

    public function canAccessGroup(?User $user, CommunityGroup $group): bool
    {
        if (! $user || $this->enumValue($group->getAttribute('status')) !== CommunityContentStatus::Approved->value) {
            return false;
        }

        if ($group->requires_paid_access && $group->course && ! $this->courseAccess->canAccessCourse($user, $group->course)) {
            return false;
        }

        if ($this->enumValue($group->getAttribute('visibility')) === CommunityVisibility::PaidMembers->value && $group->course) {
            return $this->courseAccess->canAccessCourse($user, $group->course);
        }

        return true;
    }

    public function isGroupMember(User $user, CommunityGroup $group): bool
    {
        return $group->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
