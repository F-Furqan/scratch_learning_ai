<?php

namespace Database\Seeders;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsFunnelSnapshot;
use App\Models\BlogPost;
use App\Models\CohortRetentionSnapshot;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AnalyticsReportingSeeder extends Seeder
{
    /**
     * Seed starter BI events and snapshots for local dashboards.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first() ?? User::query()->first();
        $course = Course::query()->where('slug', 'industrial-laravel-foundations')->first();
        $lesson = $course ? CourseLesson::query()->where('course_id', $course->id)->first() : null;
        $post = BlogPost::query()->where('slug', 'building-industrial-learning-platforms')->first();

        $visitorId = 'seed-'.Str::lower(Str::random(12));

        foreach ([
            AnalyticsEventType::LandingPageView,
            AnalyticsEventType::CourseListingView,
            AnalyticsEventType::CourseView,
            AnalyticsEventType::CheckoutStarted,
            AnalyticsEventType::CheckoutCompleted,
            AnalyticsEventType::LessonCompleted,
        ] as $index => $type) {
            AnalyticsEvent::query()->firstOrCreate(
                [
                    'visitor_id' => $visitorId,
                    'event_type' => $type->value,
                    'occurred_at' => now()->subDays(6 - $index),
                ],
                [
                    'user_id' => $user?->id,
                    'session_id' => 'seed-session',
                    'event_name' => $type->value,
                    'eventable_type' => $course && in_array($type, [AnalyticsEventType::CourseView, AnalyticsEventType::CheckoutStarted, AnalyticsEventType::CheckoutCompleted, AnalyticsEventType::LessonCompleted], true) ? $course->getMorphClass() : null,
                    'eventable_id' => $course && in_array($type, [AnalyticsEventType::CourseView, AnalyticsEventType::CheckoutStarted, AnalyticsEventType::CheckoutCompleted, AnalyticsEventType::LessonCompleted], true) ? $course->id : null,
                    'course_id' => $course?->id,
                    'course_lesson_id' => $lesson?->id,
                    'blog_post_id' => null,
                    'url' => url('/'),
                    'source' => 'seed',
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        if ($post) {
            AnalyticsEvent::query()->firstOrCreate(
                [
                    'visitor_id' => $visitorId,
                    'event_type' => AnalyticsEventType::BlogView->value,
                    'blog_post_id' => $post->id,
                ],
                [
                    'user_id' => $user?->id,
                    'session_id' => 'seed-session',
                    'event_name' => AnalyticsEventType::BlogView->value,
                    'eventable_type' => $post->getMorphClass(),
                    'eventable_id' => $post->id,
                    'occurred_at' => now()->subDays(2),
                    'url' => route('public.blog.show', $post->slug),
                    'source' => 'seed',
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        $cohortMonth = CourseEnrollment::query()
            ->whereNotNull('started_at')
            ->oldest('started_at')
            ->value('started_at');

        CohortRetentionSnapshot::query()->updateOrCreate(
            ['cohort_month' => $cohortMonth ? Carbon::parse($cohortMonth)->startOfMonth()->toDateString() : today()->startOfMonth()->toDateString(), 'period_number' => 0],
            [
                'users_count' => 24,
                'retained_users_count' => 24,
                'retention_rate_basis_points' => 10000,
                'metadata' => ['seeded' => true],
            ],
        );

        foreach ([
            ['stage' => 'landing_page_view', 'order' => 1, 'visitors' => 500, 'conversions' => 410],
            ['stage' => 'course_view', 'order' => 2, 'visitors' => 410, 'conversions' => 120],
            ['stage' => 'checkout_started', 'order' => 3, 'visitors' => 120, 'conversions' => 82],
            ['stage' => 'checkout_completed', 'order' => 4, 'visitors' => 82, 'conversions' => 55],
            ['stage' => 'lesson_completed', 'order' => 5, 'visitors' => 55, 'conversions' => 55],
        ] as $stage) {
            AnalyticsFunnelSnapshot::query()->updateOrCreate(
                [
                    'funnel_key' => 'public_to_paid_learning',
                    'period_date' => today()->toDateString(),
                    'period' => 'daily',
                    'stage' => $stage['stage'],
                ],
                [
                    'stage_order' => $stage['order'],
                    'visitors_count' => $stage['visitors'],
                    'users_count' => $stage['visitors'],
                    'conversions_count' => $stage['conversions'],
                    'conversion_rate_basis_points' => (int) round(($stage['conversions'] / max(1, $stage['visitors'])) * 10000),
                    'metadata' => ['seeded' => true],
                ],
            );
        }
    }
}
