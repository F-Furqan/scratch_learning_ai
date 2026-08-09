<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BookOpen,
    ChartNoAxesColumnIncreasing,
    FileText,
    GraduationCap,
    MousePointerClick,
    Newspaper,
    PenLine,
    ReceiptText,
    Repeat2,
    Route,
    Target,
    Users,
} from '@lucide/vue';

type CountRow = {
    key: string;
    count: number;
};

type Reports = {
    range: {
        from: string;
        to: string;
    };
    revenue: {
        total: number;
        spend: number;
        activeCampaigns: number;
        pendingAdvertisers: number;
        currency: string;
    };
    engagement: {
        impressions: number;
        clicks: number;
        ctr: number;
    };
    content: {
        publishedCourses: number;
        publishedLessons: number;
        publishedPosts: number;
        publishedPages: number;
        courseComments: number;
        blogComments: number;
    };
    topCampaigns: Array<{
        campaign: string;
        impressions: number;
        clicks: number;
        revenue: number;
    }>;
    studentEngagement: {
        activeStudents: number;
        lessonStarts: number;
        lessonCompletions: number;
        completionRate: number;
        averageProgressPercent: number;
        watchSeconds: number;
        topCourses: Array<{
            course: string;
            slug: string | null;
            activeStudents: number;
            progressEvents: number;
            completions: number;
            averageProgressPercent: number;
        }>;
    };
    completionDropOff: {
        courses: Array<{
            course: string;
            slug: string;
            started: number;
            completed: number;
            dropOffs: number;
            completionRate: number;
            publishedLessons: number;
        }>;
        lessons: Array<{
            lesson: string;
            slug: string | null;
            started: number;
            completed: number;
            dropOffRate: number;
        }>;
    };
    commerceRevenue: {
        currency: string;
        totalCents: number;
        ordersCount: number;
        averageOrderValueCents: number;
        byDate: Array<{
            date: string;
            orders: number;
            revenueCents: number;
        }>;
        byCourse: Array<{
            course: string;
            slug: string | null;
            orders: number;
            revenueCents: number;
        }>;
        byPlan: Array<{
            plan: string;
            productType: string | null;
            orders: number;
            revenueCents: number;
        }>;
        bySubscription: Array<{
            subscription: string;
            orders: number;
            revenueCents: number;
        }>;
    };
    paddleReconciliation: {
        records: number;
        reconciled: number;
        pending: number;
        failedEvents: number;
        byStatus: CountRow[];
        byRecordType: CountRow[];
        recentFailures: Array<{
            eventId: string;
            eventType: string;
            attempts: number;
            lastError: string | null;
        }>;
    };
    cohortRetention: {
        source: string;
        cohorts: Array<{
            cohort: string;
            periodNumber: number;
            users: number;
            retainedUsers: number;
            retentionRate: number;
        }>;
    };
    funnel: {
        source: string;
        stages: Array<{
            stage: string;
            stageKey?: string;
            stageOrder: number;
            visitors: number;
            users: number;
            conversionRate: number;
        }>;
    };
    authorPerformance: {
        authors: Array<{
            author: string;
            email: string | null;
            blogViews: number;
            courseViews: number;
            lessonViews: number;
            comments: number;
            enrollments: number;
            courseRevenueCents: number;
            engagementScore: number;
        }>;
        blogs: Array<{
            title: string;
            slug: string;
            author: string | null;
            views: number;
            comments: number;
            isFeatured: boolean;
        }>;
    };
};

const props = defineProps<{
    reports: Reports;
}>();

function money(value: number, currency: string) {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function moneyCents(value: number, currency: string) {
    return money(value / 100, currency);
}

function numberValue(value: number) {
    return new Intl.NumberFormat('en').format(value);
}

function percent(value: number) {
    return `${value.toFixed(value % 1 === 0 ? 0 : 2)}%`;
}

function duration(seconds: number) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
}
</script>

<template>
    <Head title="Reports" />

    <div class="min-h-screen bg-slate-50 p-6">
        <div class="mx-auto grid max-w-7xl gap-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p
                        class="text-sm font-semibold tracking-normal text-slate-500 uppercase"
                    >
                        {{ props.reports.range.from }} to
                        {{ props.reports.range.to }}
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-950">
                        Analytics and reporting pro
                    </h1>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <p>Funnel source: {{ props.reports.funnel.source }}</p>
                    <p>
                        Retention source:
                        {{ props.reports.cohortRetention.source }}
                    </p>
                </div>
            </div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">
                            Paid Revenue
                        </p>
                        <BadgeDollarSign class="h-5 w-5 text-emerald-700" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">
                        {{
                            moneyCents(
                                props.reports.commerceRevenue.totalCents,
                                props.reports.commerceRevenue.currency,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{
                            numberValue(props.reports.commerceRevenue.ordersCount)
                        }}
                        orders
                    </p>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">
                            Active Students
                        </p>
                        <Users class="h-5 w-5 text-sky-700" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">
                        {{
                            numberValue(
                                props.reports.studentEngagement.activeStudents,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{
                            percent(
                                props.reports.studentEngagement.completionRate,
                            )
                        }}
                        lesson completion
                    </p>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">
                            Paddle Reconciled
                        </p>
                        <ReceiptText class="h-5 w-5 text-violet-700" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">
                        {{
                            numberValue(
                                props.reports.paddleReconciliation.reconciled,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{
                            numberValue(
                                props.reports.paddleReconciliation.failedEvents,
                            )
                        }}
                        failed webhook events
                    </p>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">
                            Ad Revenue
                        </p>
                        <Target class="h-5 w-5 text-amber-700" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">
                        {{
                            money(
                                props.reports.revenue.total,
                                props.reports.revenue.currency,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ percent(props.reports.engagement.ctr) }} CTR
                    </p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Student engagement
                        </h2>
                        <GraduationCap class="h-5 w-5 text-slate-500" />
                    </div>
                    <div
                        class="mt-5 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div class="rounded-md bg-slate-50 p-3">
                            <p class="text-slate-500">Starts</p>
                            <strong class="text-lg text-slate-950">{{
                                numberValue(
                                    props.reports.studentEngagement.lessonStarts,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3">
                            <p class="text-slate-500">Completions</p>
                            <strong class="text-lg text-slate-950">{{
                                numberValue(
                                    props.reports.studentEngagement
                                        .lessonCompletions,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3">
                            <p class="text-slate-500">Avg. progress</p>
                            <strong class="text-lg text-slate-950">{{
                                percent(
                                    props.reports.studentEngagement
                                        .averageProgressPercent,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3">
                            <p class="text-slate-500">Watch time</p>
                            <strong class="text-lg text-slate-950">{{
                                duration(
                                    props.reports.studentEngagement.watchSeconds,
                                )
                            }}</strong>
                        </div>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Course</th>
                                    <th class="py-3 pr-4">Students</th>
                                    <th class="py-3 pr-4">Events</th>
                                    <th class="py-3 pr-4">Completions</th>
                                    <th class="py-3">Avg.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="course in props.reports
                                        .studentEngagement.topCourses"
                                    :key="course.slug ?? course.course"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ course.course }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.activeStudents) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.progressEvents) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.completions) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{
                                            percent(
                                                course.averageProgressPercent,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.studentEngagement
                                            .topCourses.length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No student activity in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Funnel
                        </h2>
                        <Route class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 grid gap-3">
                        <div
                            v-for="stage in props.reports.funnel.stages"
                            :key="stage.stage"
                            class="grid grid-cols-[2rem_1fr_auto] items-center gap-3 rounded-md bg-slate-50 p-3 text-sm"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-white text-xs font-semibold text-slate-700"
                            >
                                {{ stage.stageOrder }}
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate font-medium text-slate-900"
                                >
                                    {{ stage.stage }}
                                </p>
                                <p class="text-slate-500">
                                    {{ numberValue(stage.visitors) }} visitors ·
                                    {{ numberValue(stage.users) }} users
                                </p>
                            </div>
                            <strong class="text-slate-950">{{
                                percent(stage.conversionRate)
                            }}</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Completion and drop-off
                        </h2>
                        <Repeat2 class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Course</th>
                                    <th class="py-3 pr-4">Started</th>
                                    <th class="py-3 pr-4">Done</th>
                                    <th class="py-3 pr-4">Drop-offs</th>
                                    <th class="py-3">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="course in props.reports
                                        .completionDropOff.courses"
                                    :key="course.slug"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ course.course }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.started) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.completed) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.dropOffs) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{ percent(course.completionRate) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.completionDropOff.courses
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No course completion data in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Lesson</th>
                                    <th class="py-3 pr-4">Started</th>
                                    <th class="py-3 pr-4">Done</th>
                                    <th class="py-3">Drop-off</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="lesson in props.reports
                                        .completionDropOff.lessons"
                                    :key="lesson.slug ?? lesson.lesson"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ lesson.lesson }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(lesson.started) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(lesson.completed) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{ percent(lesson.dropOffRate) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.completionDropOff.lessons
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="4"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No lesson drop-off data in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Revenue
                        </h2>
                        <BadgeDollarSign class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="text-slate-500">AOV</p>
                            <strong class="text-lg text-slate-950">{{
                                moneyCents(
                                    props.reports.commerceRevenue
                                        .averageOrderValueCents,
                                    props.reports.commerceRevenue.currency,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="text-slate-500">Ad spend</p>
                            <strong class="text-lg text-slate-950">{{
                                money(
                                    props.reports.revenue.spend,
                                    props.reports.revenue.currency,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="text-slate-500">Pending ads</p>
                            <strong class="text-lg text-slate-950">{{
                                numberValue(
                                    props.reports.revenue.pendingAdvertisers,
                                )
                            }}</strong>
                        </div>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Course</th>
                                    <th class="py-3 pr-4">Orders</th>
                                    <th class="py-3">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="course in props.reports
                                        .commerceRevenue.byCourse"
                                    :key="course.slug ?? course.course"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ course.course }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(course.orders) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{
                                            moneyCents(
                                                course.revenueCents,
                                                props.reports.commerceRevenue
                                                    .currency,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.commerceRevenue.byCourse
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="3"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No course revenue in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-5 grid gap-3 lg:grid-cols-2">
                        <div class="rounded-md bg-slate-50 p-3">
                            <h3 class="text-sm font-semibold text-slate-900">
                                Plans
                            </h3>
                            <div class="mt-3 grid gap-2">
                                <div
                                    v-for="plan in props.reports
                                        .commerceRevenue.byPlan"
                                    :key="plan.plan"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="truncate text-slate-600">{{
                                        plan.plan
                                    }}</span>
                                    <strong class="text-slate-950">{{
                                        moneyCents(
                                            plan.revenueCents,
                                            props.reports.commerceRevenue
                                                .currency,
                                        )
                                    }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3">
                            <h3 class="text-sm font-semibold text-slate-900">
                                Subscriptions
                            </h3>
                            <div class="mt-3 grid gap-2">
                                <div
                                    v-for="subscription in props.reports
                                        .commerceRevenue.bySubscription"
                                    :key="subscription.subscription"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="truncate text-slate-600">{{
                                        subscription.subscription
                                    }}</span>
                                    <strong class="text-slate-950">{{
                                        moneyCents(
                                            subscription.revenueCents,
                                            props.reports.commerceRevenue
                                                .currency,
                                        )
                                    }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Paddle reconciliation
                        </h2>
                        <ReceiptText class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="text-slate-500">Records</p>
                            <strong class="text-lg text-slate-950">{{
                                numberValue(
                                    props.reports.paddleReconciliation.records,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="text-slate-500">Pending</p>
                            <strong class="text-lg text-slate-950">{{
                                numberValue(
                                    props.reports.paddleReconciliation.pending,
                                )
                            }}</strong>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Status
                            </h3>
                            <div class="mt-3 grid gap-2">
                                <div
                                    v-for="status in props.reports
                                        .paddleReconciliation.byStatus"
                                    :key="status.key"
                                    class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm"
                                >
                                    <span class="text-slate-600">{{
                                        status.key
                                    }}</span>
                                    <strong>{{ numberValue(status.count) }}</strong>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Record type
                            </h3>
                            <div class="mt-3 grid gap-2">
                                <div
                                    v-for="type in props.reports
                                        .paddleReconciliation.byRecordType"
                                    :key="type.key"
                                    class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm"
                                >
                                    <span class="text-slate-600">{{
                                        type.key
                                    }}</span>
                                    <strong>{{ numberValue(type.count) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Event</th>
                                    <th class="py-3 pr-4">Type</th>
                                    <th class="py-3">Attempts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="failure in props.reports
                                        .paddleReconciliation.recentFailures"
                                    :key="failure.eventId"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ failure.eventId }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ failure.eventType }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{ numberValue(failure.attempts) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Cohort retention
                        </h2>
                        <ChartNoAxesColumnIncreasing
                            class="h-5 w-5 text-slate-500"
                        />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Cohort</th>
                                    <th class="py-3 pr-4">Period</th>
                                    <th class="py-3 pr-4">Users</th>
                                    <th class="py-3 pr-4">Retained</th>
                                    <th class="py-3">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="cohort in props.reports
                                        .cohortRetention.cohorts"
                                    :key="`${cohort.cohort}-${cohort.periodNumber}`"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ cohort.cohort }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ cohort.periodNumber }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(cohort.users) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(cohort.retainedUsers) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{ percent(cohort.retentionRate) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.cohortRetention.cohorts
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No retention cohorts in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Author contribution
                        </h2>
                        <PenLine class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Author</th>
                                    <th class="py-3 pr-4">Blogs</th>
                                    <th class="py-3 pr-4">Courses</th>
                                    <th class="py-3 pr-4">Enrollments</th>
                                    <th class="py-3">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="author in props.reports
                                        .authorPerformance.authors"
                                    :key="author.email ?? author.author"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ author.author }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(author.blogViews) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(author.courseViews) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(author.enrollments) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{
                                            moneyCents(
                                                author.courseRevenueCents,
                                                props.reports.commerceRevenue
                                                    .currency,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.authorPerformance.authors
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No author snapshots in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Blog performance
                        </h2>
                        <Newspaper class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Post</th>
                                    <th class="py-3 pr-4">Author</th>
                                    <th class="py-3 pr-4">Views</th>
                                    <th class="py-3">Comments</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="post in props.reports
                                        .authorPerformance.blogs"
                                    :key="post.slug"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ post.title }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ post.author ?? 'Unassigned' }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(post.views) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{ numberValue(post.comments) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.reports.authorPerformance.blogs
                                            .length === 0
                                    "
                                >
                                    <td
                                        colspan="4"
                                        class="py-8 text-center text-slate-500"
                                    >
                                        No blog performance data in this range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Top ad campaigns
                        </h2>
                        <MousePointerClick class="h-5 w-5 text-slate-500" />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="py-3 pr-4">Campaign</th>
                                    <th class="py-3 pr-4">Impressions</th>
                                    <th class="py-3 pr-4">Clicks</th>
                                    <th class="py-3">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="campaign in props.reports
                                        .topCampaigns"
                                    :key="campaign.campaign"
                                >
                                    <td
                                        class="py-3 pr-4 font-medium text-slate-900"
                                    >
                                        {{ campaign.campaign }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(campaign.impressions) }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">
                                        {{ numberValue(campaign.clicks) }}
                                    </td>
                                    <td class="py-3 text-slate-900">
                                        {{
                                            money(
                                                campaign.revenue,
                                                props.reports.revenue.currency,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-lg font-semibold text-slate-950">
                        Content inventory
                    </h2>
                    <div class="mt-5 grid gap-3">
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 p-3"
                        >
                            <span
                                class="inline-flex min-w-0 items-center gap-2 text-sm text-slate-600"
                            >
                                <BookOpen class="h-4 w-4 shrink-0" />
                                Published courses
                            </span>
                            <strong>{{
                                numberValue(
                                    props.reports.content.publishedCourses,
                                )
                            }}</strong>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 p-3"
                        >
                            <span
                                class="inline-flex min-w-0 items-center gap-2 text-sm text-slate-600"
                            >
                                <BookOpen class="h-4 w-4 shrink-0" />
                                Published lessons
                            </span>
                            <strong>{{
                                numberValue(
                                    props.reports.content.publishedLessons,
                                )
                            }}</strong>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 p-3"
                        >
                            <span
                                class="inline-flex min-w-0 items-center gap-2 text-sm text-slate-600"
                            >
                                <Newspaper class="h-4 w-4 shrink-0" />
                                Published posts
                            </span>
                            <strong>{{
                                numberValue(
                                    props.reports.content.publishedPosts,
                                )
                            }}</strong>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 p-3"
                        >
                            <span
                                class="inline-flex min-w-0 items-center gap-2 text-sm text-slate-600"
                            >
                                <FileText class="h-4 w-4 shrink-0" />
                                Published pages
                            </span>
                            <strong>{{
                                numberValue(
                                    props.reports.content.publishedPages,
                                )
                            }}</strong>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
