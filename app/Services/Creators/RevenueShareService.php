<?php

namespace App\Services\Creators;

use App\Enums\RevenueShareRuleStatus;
use App\Models\Course;
use App\Models\RevenueShareRule;
use App\Models\User;

class RevenueShareService
{
    public function activeRuleForCourse(Course $course, ?User $instructor = null): ?RevenueShareRule
    {
        return RevenueShareRule::query()
            ->where('status', RevenueShareRuleStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($query) use ($course, $instructor): void {
                $query->where('course_id', $course->id)
                    ->when($instructor, fn ($query) => $query->orWhere('user_id', $instructor->id))
                    ->orWhere(function ($query): void {
                        $query->whereNull('course_id')->whereNull('user_id');
                    });
            })
            ->orderByRaw('course_id is null')
            ->orderByRaw('user_id is null')
            ->latest()
            ->first();
    }

    public function calculateShareCents(int $grossCents, RevenueShareRule $rule): int
    {
        $percentageAmount = (int) round(max(0, $grossCents) * (((float) $rule->share_percent) / 100));
        $fixedAmount = (int) ($rule->fixed_amount_cents ?? 0);

        return min(max(0, $grossCents), $percentageAmount + $fixedAmount);
    }
}
