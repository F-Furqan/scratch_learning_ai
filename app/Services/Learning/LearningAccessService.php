<?php

namespace App\Services\Learning;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\DripReleaseType;
use App\Enums\LearningResourceAccess;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\LessonDripSchedule;
use App\Models\User;
use App\Services\Payments\CourseAccessService;
use BackedEnum;
use Carbon\CarbonInterface;

class LearningAccessService
{
    public function __construct(
        private readonly CourseAccessService $courseAccess,
    ) {}

    public function canAccessCourse(?User $user, Course $course): bool
    {
        return $this->courseAccess->canAccessCourse($user, $course);
    }

    public function canAccessLesson(?User $user, Course $course, CourseLesson $lesson): bool
    {
        return $this->courseAccess->canAccessLesson($user, $course, $lesson)
            && $this->lessonIsReleased($user, $course, $lesson);
    }

    public function lessonIsReleased(?User $user, Course $course, CourseLesson $lesson): bool
    {
        $availableAt = $this->lessonAvailableAt($user, $course, $lesson);

        return $availableAt === null || $availableAt->lessThanOrEqualTo(now());
    }

    public function lessonAvailableAt(?User $user, Course $course, CourseLesson $lesson): ?CarbonInterface
    {
        $schedule = $this->dripSchedule($lesson);

        if (! $schedule || ! (bool) $schedule->is_active) {
            return null;
        }

        return match ($this->enumValue($schedule->getAttribute('release_type'))) {
            DripReleaseType::SpecificDate->value => $this->carbonValue($schedule->getAttribute('release_at')),
            DripReleaseType::DaysAfterEnrollment->value => $this->availableAfterEnrollment($user, $course, (int) $schedule->release_after_days),
            default => null,
        };
    }

    public function canAccessResource(?User $user, CourseResource $resource): bool
    {
        if (! (bool) $resource->is_active) {
            return false;
        }

        $accessLevel = $this->resourceAccessLevel($resource);

        if ($accessLevel === LearningResourceAccess::Free) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $course = $resource->course;

        if (! $course) {
            return false;
        }

        if ($resource->course_lesson_id) {
            $lesson = $resource->lesson;

            if ($lesson && ! $this->canAccessLesson($user, $course, $lesson)) {
                return false;
            }
        }

        if ($accessLevel === LearningResourceAccess::Purchased) {
            return $this->courseAccess->canAccessCourse($user, $course);
        }

        return $this->hasEnrollment($user, $course)
            || $this->courseAccess->canAccessCourse($user, $course);
    }

    private function availableAfterEnrollment(?User $user, Course $course, int $days): ?CarbonInterface
    {
        if (! $user) {
            return now()->addDays(max(0, $days));
        }

        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [
                CourseEnrollmentStatus::Active->value,
                CourseEnrollmentStatus::Completed->value,
            ])
            ->first();

        if (! $enrollment) {
            return null;
        }

        $startedAt = $enrollment->getAttribute('started_at') ?? $enrollment->getAttribute('created_at');

        return $startedAt instanceof CarbonInterface ? $startedAt->copy()->addDays(max(0, $days)) : null;
    }

    private function hasEnrollment(User $user, Course $course): bool
    {
        return CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [
                CourseEnrollmentStatus::Active->value,
                CourseEnrollmentStatus::Completed->value,
            ])
            ->exists();
    }

    private function dripSchedule(CourseLesson $lesson): ?LessonDripSchedule
    {
        if ($lesson->relationLoaded('dripSchedule')) {
            $schedule = $lesson->getRelation('dripSchedule');

            return $schedule instanceof LessonDripSchedule ? $schedule : null;
        }

        return LessonDripSchedule::query()
            ->where('course_lesson_id', $lesson->id)
            ->first();
    }

    private function resourceAccessLevel(CourseResource $resource): LearningResourceAccess
    {
        $value = $resource->getAttribute('access_level');

        if ($value instanceof LearningResourceAccess) {
            return $value;
        }

        return LearningResourceAccess::tryFrom((string) $value) ?? LearningResourceAccess::Enrolled;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function carbonValue(mixed $value): ?CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : null;
    }
}
