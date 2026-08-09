<?php

namespace App\Services\Payments;

use App\Enums\CoursePurchaseStatus;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use App\Enums\TeamAccountStatus;
use App\Enums\TeamSeatStatus;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CoursePurchase;
use App\Models\PaymentEntitlement;
use App\Models\TeamSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CourseAccessService
{
    public function canAccessCourse(?User $user, Course $course): bool
    {
        if ((bool) $course->is_free) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $this->ownsCourse($user, $course)
            || $this->hasCourseEntitlement($user, $course)
            || $this->hasPremiumLibrary($user)
            || $this->hasTeamEntitlement($user, PaymentEntitlementType::Course, $course)
            || $this->hasTeamEntitlement($user, PaymentEntitlementType::PremiumLibrary);
    }

    public function canAccessLesson(?User $user, Course $course, CourseLesson $lesson): bool
    {
        if ((bool) $lesson->is_free || ! (bool) $lesson->is_paid) {
            return true;
        }

        return $this->canAccessCourse($user, $course);
    }

    public function hasPremiumLibrary(User $user): bool
    {
        return $this->activeUserEntitlement($user, PaymentEntitlementType::PremiumLibrary)->exists();
    }

    public function hasAdFreePlan(User $user): bool
    {
        return $this->activeUserEntitlement($user, PaymentEntitlementType::AdFree)->exists()
            || $this->hasTeamEntitlement($user, PaymentEntitlementType::AdFree);
    }

    private function ownsCourse(User $user, Course $course): bool
    {
        return CoursePurchase::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', CoursePurchaseStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function hasCourseEntitlement(User $user, Course $course): bool
    {
        return $this->activeUserEntitlement($user, PaymentEntitlementType::Course)
            ->where('entitlementable_type', $course->getMorphClass())
            ->where('entitlementable_id', $course->id)
            ->exists();
    }

    private function hasTeamEntitlement(User $user, PaymentEntitlementType $type, ?Course $course = null): bool
    {
        $teamIds = TeamSeat::query()
            ->where('user_id', $user->id)
            ->where('status', TeamSeatStatus::Active->value)
            ->whereHas('teamAccount', fn ($query) => $query->whereIn('status', [
                TeamAccountStatus::Active->value,
                TeamAccountStatus::Trialing->value,
            ]))
            ->pluck('team_account_id');

        if ($teamIds->isEmpty()) {
            return false;
        }

        return PaymentEntitlement::query()
            ->whereIn('team_account_id', $teamIds)
            ->where('type', $type->value)
            ->where('status', PaymentEntitlementStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->when($course, fn ($query) => $query
                ->where('entitlementable_type', $course->getMorphClass())
                ->where('entitlementable_id', $course->id))
            ->exists();
    }

    /**
     * @return Builder<PaymentEntitlement>
     */
    private function activeUserEntitlement(User $user, PaymentEntitlementType $type): Builder
    {
        return PaymentEntitlement::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('status', PaymentEntitlementStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}
