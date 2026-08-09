<?php

namespace App\Services\Creators;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\InstructorProfile;
use App\Models\PaymentOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

class CreatorAnalyticsService
{
    /**
     * @return array<string, int>
     */
    public function summaryFor(User $user): array
    {
        $snapshot = CreatorAnalyticsSnapshot::query()
            ->where('user_id', $user->id)
            ->latest('period_date')
            ->first();

        $blogCount = BlogPost::query()->where('author_id', $user->id)->count();
        $courseCount = Course::query()->where('created_by', $user->id)->count();

        if ($snapshot instanceof CreatorAnalyticsSnapshot) {
            return [
                'blog_posts' => $blogCount,
                'courses' => $courseCount,
                'blog_views' => (int) $snapshot->blog_views,
                'course_views' => (int) $snapshot->course_views,
                'lesson_views' => (int) $snapshot->lesson_views,
                'comments_count' => (int) $snapshot->comments_count,
                'enrollments_count' => (int) $snapshot->enrollments_count,
                'course_revenue_cents' => (int) $snapshot->course_revenue_cents,
                'ad_revenue_cents' => (int) $snapshot->ad_revenue_cents,
                'engagement_score' => (int) $snapshot->engagement_score,
            ];
        }

        return [
            'blog_posts' => $blogCount,
            'courses' => $courseCount,
            'blog_views' => 0,
            'course_views' => 0,
            'lesson_views' => 0,
            'comments_count' => $this->commentsFor($user),
            'enrollments_count' => $this->enrollmentsFor($user),
            'course_revenue_cents' => $this->courseRevenueFor($user),
            'ad_revenue_cents' => 0,
            'engagement_score' => 0,
        ];
    }

    public function snapshot(User $user, ?Carbon $date = null): CreatorAnalyticsSnapshot
    {
        $date ??= today();
        $profile = $user->instructorProfile;
        $summary = $this->summaryFor($user);

        return CreatorAnalyticsSnapshot::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'period_date' => $date->toDateString(),
                'period' => 'daily',
            ],
            [
                'instructor_profile_id' => $profile instanceof InstructorProfile ? $profile->id : null,
                'blog_views' => $summary['blog_views'],
                'course_views' => $summary['course_views'],
                'lesson_views' => $summary['lesson_views'],
                'comments_count' => $summary['comments_count'],
                'enrollments_count' => $summary['enrollments_count'],
                'course_revenue_cents' => $summary['course_revenue_cents'],
                'ad_revenue_cents' => $summary['ad_revenue_cents'],
                'engagement_score' => $this->engagementScore($summary),
                'metadata' => [
                    'blog_posts' => $summary['blog_posts'],
                    'courses' => $summary['courses'],
                ],
            ],
        );
    }

    private function commentsFor(User $user): int
    {
        return BlogComment::query()
            ->whereHas('post', fn ($query) => $query->where('author_id', $user->id))
            ->count();
    }

    private function enrollmentsFor(User $user): int
    {
        return CourseEnrollment::query()
            ->whereHas('course', fn ($query) => $query->where('created_by', $user->id))
            ->count();
    }

    private function courseRevenueFor(User $user): int
    {
        return (int) PaymentOrderItem::query()
            ->whereHas('course', fn ($query) => $query->where('created_by', $user->id))
            ->sum('total');
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function engagementScore(array $summary): int
    {
        return min(100, (int) round(
            ($summary['comments_count'] * 2)
            + ($summary['enrollments_count'] * 3)
            + (($summary['blog_views'] + $summary['course_views'] + $summary['lesson_views']) / 100),
        ));
    }
}
