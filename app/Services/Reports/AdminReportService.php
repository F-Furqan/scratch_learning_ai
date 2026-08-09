<?php

namespace App\Services\Reports;

use App\Enums\AdCampaignStatus;
use App\Enums\AnalyticsEventType;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentOrderStatus;
use App\Enums\PublishStatus;
use App\Models\AdCampaign;
use App\Models\AdCampaignReport;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\AdvertiserRequest;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsFunnelSnapshot;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\CohortRetentionSnapshot;
use App\Models\Course;
use App\Models\CourseComment;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $fromDate = Carbon::parse($from ?? now()->subDays(30))->startOfDay();
        $toDate = Carbon::parse($to ?? now())->endOfDay();

        return [
            'range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'revenue' => $this->revenue($fromDate, $toDate),
            'engagement' => $this->engagement($fromDate, $toDate),
            'content' => $this->content(),
            'topCampaigns' => $this->topCampaigns($fromDate, $toDate),
            'studentEngagement' => $this->studentEngagement($fromDate, $toDate),
            'completionDropOff' => $this->completionDropOff($fromDate, $toDate),
            'commerceRevenue' => $this->commerceRevenue($fromDate, $toDate),
            'paddleReconciliation' => $this->paddleReconciliation($fromDate, $toDate),
            'cohortRetention' => $this->cohortRetention($fromDate, $toDate),
            'funnel' => $this->funnel($fromDate, $toDate),
            'authorPerformance' => $this->authorPerformance($fromDate, $toDate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $reports = AdCampaignReport::query()->whereBetween('report_date', $this->reportDateBounds($from, $to));

        return [
            'total' => round((float) $reports->clone()->sum('revenue'), 2),
            'spend' => round((float) $reports->clone()->sum('spend'), 2),
            'activeCampaigns' => AdCampaign::query()->where('status', AdCampaignStatus::Active->value)->count(),
            'pendingAdvertisers' => AdvertiserRequest::query()->where('status', 'pending')->count(),
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function engagement(CarbonInterface $from, CarbonInterface $to): array
    {
        $impressions = AdImpression::query()->whereBetween('occurred_at', [$from, $to])->count();
        $clicks = AdClick::query()->whereBetween('occurred_at', [$from, $to])->count();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $this->percentage($clicks, $impressions),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function content(): array
    {
        return [
            'publishedCourses' => Course::query()->where('status', PublishStatus::Published->value)->count(),
            'publishedLessons' => CourseLesson::query()->where('status', PublishStatus::Published->value)->count(),
            'publishedPosts' => BlogPost::query()->where('status', PublishStatus::Published->value)->count(),
            'publishedPages' => Page::query()->where('status', PublishStatus::Published->value)->count(),
            'courseComments' => CourseComment::count(),
            'blogComments' => BlogComment::count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topCampaigns(CarbonInterface $from, CarbonInterface $to): array
    {
        return AdCampaignReport::query()
            ->select([
                'ad_campaign_id',
                DB::raw('sum(impressions) as impressions'),
                DB::raw('sum(clicks) as clicks'),
                DB::raw('sum(revenue) as revenue'),
            ])
            ->with('campaign:id,name')
            ->whereBetween('report_date', $this->reportDateBounds($from, $to))
            ->groupBy('ad_campaign_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn (AdCampaignReport $report): array => [
                'campaign' => $report->campaign?->name ?: 'Campaign #'.$report->ad_campaign_id,
                'impressions' => (int) $report->getAttribute('impressions'),
                'clicks' => (int) $report->getAttribute('clicks'),
                'revenue' => round((float) $report->getAttribute('revenue'), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function studentEngagement(CarbonInterface $from, CarbonInterface $to): array
    {
        $progress = LessonProgress::query()->whereBetween('last_watched_at', [$from, $to]);
        $lessonStarts = LessonProgress::query()->whereBetween('started_at', [$from, $to])->count();
        $lessonCompletions = LessonProgress::query()->whereBetween('completed_at', [$from, $to])->count();
        $activeStudents = $progress->clone()->distinct('user_id')->count('user_id');
        $watchSeconds = (int) $progress->clone()->sum('progress_seconds');
        $averageProgress = round((float) ($progress->clone()->avg('progress_percent') ?? 0), 1);

        return [
            'activeStudents' => $activeStudents,
            'lessonStarts' => $lessonStarts,
            'lessonCompletions' => $lessonCompletions,
            'completionRate' => $this->percentage($lessonCompletions, $lessonStarts),
            'averageProgressPercent' => $averageProgress,
            'watchSeconds' => $watchSeconds,
            'topCourses' => $this->topEngagedCourses($from, $to),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topEngagedCourses(CarbonInterface $from, CarbonInterface $to): array
    {
        return LessonProgress::query()
            ->select([
                'course_id',
                DB::raw('count(distinct user_id) as active_students'),
                DB::raw('count(*) as progress_events'),
                DB::raw("sum(case when status = '".LessonProgressStatus::Completed->value."' then 1 else 0 end) as completions"),
                DB::raw('avg(progress_percent) as average_progress'),
            ])
            ->with('course:id,title,slug')
            ->whereBetween('last_watched_at', [$from, $to])
            ->groupBy('course_id')
            ->orderByDesc('active_students')
            ->limit(8)
            ->get()
            ->map(fn (LessonProgress $progress): array => [
                'course' => $progress->course?->title ?: 'Course #'.$progress->course_id,
                'slug' => $progress->course?->slug,
                'activeStudents' => (int) $progress->getAttribute('active_students'),
                'progressEvents' => (int) $progress->getAttribute('progress_events'),
                'completions' => (int) $progress->getAttribute('completions'),
                'averageProgressPercent' => round((float) $progress->getAttribute('average_progress'), 1),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function completionDropOff(CarbonInterface $from, CarbonInterface $to): array
    {
        $courses = Course::query()
            ->withCount([
                'enrollments as started_count' => fn ($query) => $query->whereBetween('started_at', [$from, $to]),
                'enrollments as completed_count' => fn ($query) => $query->whereBetween('completed_at', [$from, $to]),
                'lessons as published_lessons_count' => fn ($query) => $query->where('status', PublishStatus::Published->value),
            ])
            ->whereHas('enrollments', fn ($query) => $query->whereBetween('started_at', [$from, $to]))
            ->orderByDesc('started_count')
            ->limit(10)
            ->get()
            ->map(fn (Course $course): array => [
                'course' => $course->title,
                'slug' => $course->slug,
                'started' => (int) $course->getAttribute('started_count'),
                'completed' => (int) $course->getAttribute('completed_count'),
                'dropOffs' => max(0, (int) $course->getAttribute('started_count') - (int) $course->getAttribute('completed_count')),
                'completionRate' => $this->percentage((int) $course->getAttribute('completed_count'), (int) $course->getAttribute('started_count')),
                'publishedLessons' => (int) $course->getAttribute('published_lessons_count'),
            ])
            ->values()
            ->all();

        $lessons = LessonProgress::query()
            ->select([
                'course_lesson_id',
                DB::raw('count(*) as started_count'),
                DB::raw("sum(case when status = '".LessonProgressStatus::Completed->value."' then 1 else 0 end) as completed_count"),
            ])
            ->with('lesson:id,course_id,title,slug,order_number')
            ->whereBetween('last_watched_at', [$from, $to])
            ->groupBy('course_lesson_id')
            ->orderByDesc('started_count')
            ->limit(10)
            ->get()
            ->map(fn (LessonProgress $progress): array => [
                'lesson' => $progress->lesson?->title ?: 'Lesson #'.$progress->course_lesson_id,
                'slug' => $progress->lesson?->slug,
                'started' => (int) $progress->getAttribute('started_count'),
                'completed' => (int) $progress->getAttribute('completed_count'),
                'dropOffRate' => round(100 - $this->percentage((int) $progress->getAttribute('completed_count'), (int) $progress->getAttribute('started_count')), 2),
            ])
            ->values()
            ->all();

        return [
            'courses' => $courses,
            'lessons' => $lessons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceRevenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::Completed->value)
            ->whereBetween('purchased_at', [$from, $to]);
        $totalCents = (int) $orders->clone()->sum('total');

        return [
            'currency' => 'USD',
            'totalCents' => $totalCents,
            'ordersCount' => $orders->clone()->count(),
            'averageOrderValueCents' => $orders->clone()->count() > 0 ? (int) round($totalCents / max(1, $orders->clone()->count())) : 0,
            'byDate' => $this->revenueByDate($from, $to),
            'byCourse' => $this->revenueByCourse($from, $to),
            'byPlan' => $this->revenueByPlan($from, $to),
            'bySubscription' => $this->revenueBySubscription($from, $to),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueByDate(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentOrder::query()
            ->select([
                DB::raw('date(purchased_at) as bucket_date'),
                DB::raw('count(*) as orders_count'),
                DB::raw('sum(total) as revenue_cents'),
            ])
            ->where('status', PaymentOrderStatus::Completed->value)
            ->whereBetween('purchased_at', [$from, $to])
            ->groupBy('bucket_date')
            ->orderBy('bucket_date')
            ->get()
            ->map(fn (PaymentOrder $order): array => [
                'date' => (string) $order->getAttribute('bucket_date'),
                'orders' => (int) $order->getAttribute('orders_count'),
                'revenueCents' => (int) $order->getAttribute('revenue_cents'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueByCourse(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentOrderItem::query()
            ->select([
                'payment_order_items.course_id',
                'courses.title as course_title',
                'courses.slug as course_slug',
                DB::raw('count(distinct payment_order_items.payment_order_id) as orders_count'),
                DB::raw('sum(payment_order_items.total) as revenue_cents'),
            ])
            ->join('payment_orders', 'payment_orders.id', '=', 'payment_order_items.payment_order_id')
            ->leftJoin('courses', 'courses.id', '=', 'payment_order_items.course_id')
            ->where('payment_orders.status', PaymentOrderStatus::Completed->value)
            ->whereBetween('payment_orders.purchased_at', [$from, $to])
            ->groupBy('payment_order_items.course_id', 'courses.title', 'courses.slug')
            ->orderByDesc('revenue_cents')
            ->limit(10)
            ->get()
            ->map(fn (PaymentOrderItem $item): array => [
                'course' => (string) ($item->getAttribute('course_title') ?: 'No course attached'),
                'slug' => $item->getAttribute('course_slug'),
                'orders' => (int) $item->getAttribute('orders_count'),
                'revenueCents' => (int) $item->getAttribute('revenue_cents'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueByPlan(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentOrderItem::query()
            ->select([
                'payment_order_items.payment_price_id',
                'payment_prices.name as price_name',
                'payment_products.name as product_name',
                'payment_products.type as product_type',
                DB::raw('count(distinct payment_order_items.payment_order_id) as orders_count'),
                DB::raw('sum(payment_order_items.total) as revenue_cents'),
            ])
            ->join('payment_orders', 'payment_orders.id', '=', 'payment_order_items.payment_order_id')
            ->leftJoin('payment_prices', 'payment_prices.id', '=', 'payment_order_items.payment_price_id')
            ->leftJoin('payment_products', 'payment_products.id', '=', 'payment_order_items.payment_product_id')
            ->where('payment_orders.status', PaymentOrderStatus::Completed->value)
            ->whereBetween('payment_orders.purchased_at', [$from, $to])
            ->groupBy('payment_order_items.payment_price_id', 'payment_prices.name', 'payment_products.name', 'payment_products.type')
            ->orderByDesc('revenue_cents')
            ->limit(10)
            ->get()
            ->map(fn (PaymentOrderItem $item): array => [
                'plan' => trim((string) $item->getAttribute('product_name').' / '.(string) $item->getAttribute('price_name')),
                'productType' => $item->getAttribute('product_type'),
                'orders' => (int) $item->getAttribute('orders_count'),
                'revenueCents' => (int) $item->getAttribute('revenue_cents'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueBySubscription(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentOrder::query()
            ->select([
                'paddle_subscription_id',
                DB::raw('count(*) as orders_count'),
                DB::raw('sum(total) as revenue_cents'),
            ])
            ->where('status', PaymentOrderStatus::Completed->value)
            ->whereNotNull('paddle_subscription_id')
            ->whereBetween('purchased_at', [$from, $to])
            ->groupBy('paddle_subscription_id')
            ->orderByDesc('revenue_cents')
            ->limit(10)
            ->get()
            ->map(fn (PaymentOrder $order): array => [
                'subscription' => (string) $order->paddle_subscription_id,
                'orders' => (int) $order->getAttribute('orders_count'),
                'revenueCents' => (int) $order->getAttribute('revenue_cents'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function paddleReconciliation(CarbonInterface $from, CarbonInterface $to): array
    {
        $records = PaymentReconciliationRecord::query()->whereBetween('created_at', [$from, $to]);
        $events = PaymentWebhookEvent::query()->whereBetween('created_at', [$from, $to]);

        return [
            'records' => $records->clone()->count(),
            'reconciled' => $records->clone()->where('status', 'reconciled')->count(),
            'pending' => $records->clone()->where('status', 'pending')->count(),
            'failedEvents' => $events->clone()->where('status', 'failed')->count(),
            'byStatus' => $this->groupCounts($records->clone(), 'status'),
            'byRecordType' => $this->groupCounts($records->clone(), 'record_type'),
            'recentFailures' => PaymentWebhookEvent::query()
                ->where('status', 'failed')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (PaymentWebhookEvent $event): array => [
                    'eventId' => $event->event_id,
                    'eventType' => $event->event_type,
                    'attempts' => $event->attempts,
                    'lastError' => $event->last_error,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cohortRetention(CarbonInterface $from, CarbonInterface $to): array
    {
        $snapshots = CohortRetentionSnapshot::query()
            ->whereBetween('cohort_month', [Carbon::parse($from)->subMonths(6)->startOfMonth(), Carbon::parse($to)->startOfMonth()])
            ->orderBy('cohort_month')
            ->orderBy('period_number')
            ->get();

        if ($snapshots->isNotEmpty()) {
            $cohorts = [];

            foreach ($snapshots as $snapshot) {
                $cohorts[] = [
                    'cohort' => Carbon::parse($snapshot->getAttribute('cohort_month'))->toDateString(),
                    'periodNumber' => $snapshot->period_number,
                    'users' => $snapshot->users_count,
                    'retainedUsers' => $snapshot->retained_users_count,
                    'retentionRate' => round($snapshot->retention_rate_basis_points / 100, 2),
                ];
            }

            return [
                'source' => 'snapshots',
                'cohorts' => $cohorts,
            ];
        }

        $enrollments = CourseEnrollment::query()
            ->whereNotNull('started_at')
            ->whereBetween('started_at', [Carbon::parse($to)->subMonths(6)->startOfMonth(), $to])
            ->get(['user_id', 'started_at', 'completed_at']);

        $cohorts = $enrollments
            ->groupBy(fn (CourseEnrollment $enrollment): string => Carbon::parse($enrollment->started_at)->startOfMonth()->toDateString())
            ->map(function ($cohortEnrollments, string $cohort) use ($from, $to): array {
                $userIds = $cohortEnrollments->pluck('user_id')->unique()->values();
                $retained = LessonProgress::query()
                    ->whereIn('user_id', $userIds)
                    ->whereBetween('last_watched_at', [$from, $to])
                    ->distinct('user_id')
                    ->count('user_id');

                return [
                    'cohort' => $cohort,
                    'periodNumber' => max(0, Carbon::parse($cohort)->diffInMonths(Carbon::parse($to)->startOfMonth())),
                    'users' => $userIds->count(),
                    'retainedUsers' => $retained,
                    'retentionRate' => $this->percentage($retained, $userIds->count()),
                ];
            })
            ->values()
            ->all();

        return [
            'source' => 'live',
            'cohorts' => $cohorts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function funnel(CarbonInterface $from, CarbonInterface $to): array
    {
        $snapshots = AnalyticsFunnelSnapshot::query()
            ->where('funnel_key', 'public_to_paid_learning')
            ->whereBetween('period_date', $this->reportDateBounds($from, $to))
            ->orderBy('stage_order')
            ->get();

        if ($snapshots->isNotEmpty()) {
            return [
                'source' => 'snapshots',
                'stages' => $snapshots->groupBy('stage')->map(function ($stageSnapshots): array {
                    /** @var AnalyticsFunnelSnapshot $first */
                    $first = $stageSnapshots->first();
                    $visitors = (int) $stageSnapshots->sum('visitors_count');

                    return [
                        'stage' => $this->funnelStageLabel((string) $first->stage),
                        'stageKey' => (string) $first->stage,
                        'stageOrder' => $first->stage_order,
                        'visitors' => $visitors,
                        'users' => (int) $stageSnapshots->sum('users_count'),
                        'conversionRate' => round(((int) $stageSnapshots->sum('conversion_rate_basis_points')) / max(1, $stageSnapshots->count()) / 100, 2),
                    ];
                })->sortBy('stageOrder')->values()->all(),
            ];
        }

        $stageTypes = [
            ['stage' => AnalyticsEventType::LandingPageView->value],
            ['stage' => AnalyticsEventType::CourseView->value],
            ['stage' => AnalyticsEventType::CheckoutStarted->value],
            ['stage' => AnalyticsEventType::CheckoutCompleted->value],
            ['stage' => AnalyticsEventType::LessonCompleted->value],
        ];
        $events = AnalyticsEvent::query()
            ->whereIn('event_type', array_column($stageTypes, 'stage'))
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['event_type', 'visitor_id', 'session_id', 'user_id']);
        $previousVisitors = null;

        $stages = collect($stageTypes)->map(function (array $stage, int $index) use ($events, &$previousVisitors): array {
            $stageEvents = $events->where('event_type', $stage['stage']);
            $visitorCount = $stageEvents
                ->map(fn (AnalyticsEvent $event): string => $this->eventIdentity($event))
                ->filter()
                ->unique()
                ->count();
            $userCount = $stageEvents->pluck('user_id')->filter()->unique()->count();
            $conversionBase = $previousVisitors ?? $visitorCount;
            $conversionRate = $this->percentage($visitorCount, $conversionBase);
            $previousVisitors = $visitorCount;

            return [
                'stage' => $this->funnelStageLabel($stage['stage']),
                'stageKey' => $stage['stage'],
                'stageOrder' => $index + 1,
                'visitors' => $visitorCount,
                'users' => $userCount,
                'conversionRate' => $conversionRate,
            ];
        })->values()->all();

        return [
            'source' => 'events',
            'stages' => $stages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function authorPerformance(CarbonInterface $from, CarbonInterface $to): array
    {
        $authors = CreatorAnalyticsSnapshot::query()
            ->select([
                'user_id',
                DB::raw('sum(blog_views) as blog_views'),
                DB::raw('sum(course_views) as course_views'),
                DB::raw('sum(lesson_views) as lesson_views'),
                DB::raw('sum(comments_count) as comments_count'),
                DB::raw('sum(enrollments_count) as enrollments_count'),
                DB::raw('sum(course_revenue_cents) as course_revenue_cents'),
                DB::raw('avg(engagement_score) as engagement_score'),
            ])
            ->with('user:id,name,email')
            ->whereBetween('period_date', $this->reportDateBounds($from, $to))
            ->groupBy('user_id')
            ->orderByDesc('course_revenue_cents')
            ->limit(10)
            ->get()
            ->map(fn (CreatorAnalyticsSnapshot $snapshot): array => [
                'author' => $snapshot->user?->name ?: 'User #'.$snapshot->user_id,
                'email' => $snapshot->user?->email,
                'blogViews' => (int) $snapshot->getAttribute('blog_views'),
                'courseViews' => (int) $snapshot->getAttribute('course_views'),
                'lessonViews' => (int) $snapshot->getAttribute('lesson_views'),
                'comments' => (int) $snapshot->getAttribute('comments_count'),
                'enrollments' => (int) $snapshot->getAttribute('enrollments_count'),
                'courseRevenueCents' => (int) $snapshot->getAttribute('course_revenue_cents'),
                'engagementScore' => round((float) $snapshot->getAttribute('engagement_score'), 1),
            ])
            ->values()
            ->all();

        $blogViews = AnalyticsEvent::query()
            ->select(['blog_post_id', DB::raw('count(*) as views_count')])
            ->where('event_type', AnalyticsEventType::BlogView->value)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('blog_post_id')
            ->groupBy('blog_post_id')
            ->pluck('views_count', 'blog_post_id');

        $blogs = BlogPost::query()
            ->with(['author:id,name,email'])
            ->withCount('comments')
            ->get()
            ->map(fn (BlogPost $post): array => [
                'title' => $post->title,
                'slug' => $post->slug,
                'author' => $post->author?->name,
                'views' => (int) $blogViews->get($post->id, 0),
                'comments' => (int) $post->getAttribute('comments_count'),
                'isFeatured' => (bool) $post->is_featured,
            ])
            ->sortByDesc(fn (array $post): int => (int) $post['views'] + ((int) $post['comments'] * 3))
            ->take(10)
            ->values()
            ->all();

        return [
            'authors' => $authors,
            'blogs' => $blogs,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<int, array{key: string, count: int}>
     */
    private function groupCounts(Builder $query, string $column): array
    {
        return $query
            ->select([$column, DB::raw('count(*) as aggregate_count')])
            ->groupBy($column)
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn ($record): array => [
                'key' => (string) $record->getAttribute($column),
                'count' => (int) $record->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }

    private function eventIdentity(AnalyticsEvent $event): string
    {
        return (string) ($event->visitor_id ?: $event->session_id ?: ($event->user_id ? 'user:'.$event->user_id : 'event:'.$event->id));
    }

    private function funnelStageLabel(string $stage): string
    {
        return match ($stage) {
            AnalyticsEventType::LandingPageView->value => 'Landing views',
            AnalyticsEventType::CourseView->value => 'Course views',
            AnalyticsEventType::CheckoutStarted->value => 'Checkout starts',
            AnalyticsEventType::CheckoutCompleted->value => 'Checkout completes',
            AnalyticsEventType::LessonCompleted->value => 'Lesson completes',
            default => Str::headline($stage),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function reportDateBounds(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            $from->toDateString(),
            Carbon::parse($to)->addDay()->toDateString(),
        ];
    }

    private function percentage(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }
}
