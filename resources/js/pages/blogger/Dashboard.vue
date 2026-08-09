<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BarChart3,
    BookOpenCheck,
    CircleDollarSign,
    FileCheck2,
    FilePenLine,
    MessageSquareText,
} from '@lucide/vue';

type CreatorProfile = {
    blogger_status: string | null;
    instructor_status: string | null;
    instructor_display_name: string | null;
    is_verified_expert: boolean;
    badges: { id: number; name: string; color: string }[];
};

type CreatorAnalytics = {
    blog_posts: number;
    courses: number;
    blog_views: number;
    course_views: number;
    lesson_views: number;
    comments_count: number;
    enrollments_count: number;
    course_revenue_cents: number;
    ad_revenue_cents: number;
    engagement_score: number;
};

type Revision = {
    id: number;
    title: string;
    status: string | null;
    comments_count: number;
    submitted_at: string | null;
    scheduled_at: string | null;
};

type RevenueRule = {
    id: number;
    course_title: string | null;
    type: string | null;
    status: string | null;
    share_percent: number;
    currency: string;
};

defineProps<{
    profile: CreatorProfile;
    agreement: {
        version: string;
        accepted: boolean;
        url: string;
    };
    analytics: CreatorAnalytics;
    revisions: Revision[];
    revenue_rules: RevenueRule[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator',
                href: '/creator/dashboard',
            },
        ],
    },
});

function money(cents: number, currency = 'USD') {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
    }).format(cents / 100);
}
</script>

<template>
    <Head title="Creator Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    Creator Dashboard
                </h1>
                <p class="text-sm text-muted-foreground">
                    Editorial activity, audience signals, approval status, and
                    public profile visibility.
                </p>
            </div>
            <span
                v-if="profile.is_verified_expert"
                class="inline-flex items-center gap-2 rounded-md bg-teal-100 px-3 py-2 text-sm font-semibold text-teal-800"
            >
                <BadgeCheck class="h-4 w-4" />
                Verified expert
            </span>
        </div>

        <section
            v-if="!agreement.accepted"
            class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-950"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <FileCheck2 class="mt-0.5 h-5 w-5 text-amber-700" />
                    <div>
                        <h2 class="text-base font-semibold tracking-normal">
                            Creator agreement required
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-amber-900">
                            Accept version {{ agreement.version }} before
                            uploading courses, blogs, files, or creator
                            submissions.
                        </p>
                    </div>
                </div>
                <Link
                    :href="agreement.url"
                    class="rounded-md bg-amber-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    Review agreement
                </Link>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold">
                        {{
                            profile.instructor_display_name ||
                            'Instructor profile'
                        }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Blogger: {{ profile.blogger_status || 'none' }} /
                        Instructor: {{ profile.instructor_status || 'none' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="badge in profile.badges"
                        :key="badge.id"
                        class="rounded-md bg-muted px-2 py-1 text-xs font-semibold"
                    >
                        {{ badge.name }}
                    </span>
                </div>
            </div>
        </section>

        <div class="grid gap-3 md:grid-cols-4">
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <FilePenLine class="h-5 w-5 text-indigo-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ analytics.blog_posts }}
                        </p>
                        <p class="text-xs text-muted-foreground">Blog posts</p>
                    </div>
                </div>
            </section>
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <BookOpenCheck class="h-5 w-5 text-teal-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ analytics.courses }}
                        </p>
                        <p class="text-xs text-muted-foreground">Courses</p>
                    </div>
                </div>
            </section>
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <MessageSquareText class="h-5 w-5 text-amber-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ analytics.comments_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">Comments</p>
                    </div>
                </div>
            </section>
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <BarChart3 class="h-5 w-5 text-slate-700" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ analytics.engagement_score }}
                        </p>
                        <p class="text-xs text-muted-foreground">Engagement</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_0.85fr]">
            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <FilePenLine class="h-5 w-5 text-indigo-600" />
                    <h2 class="text-base font-semibold">Editorial Revisions</h2>
                </div>
                <div v-if="revisions.length" class="divide-y rounded-md border">
                    <div
                        v-for="revision in revisions"
                        :key="revision.id"
                        class="grid gap-1 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">{{ revision.title }}</p>
                            <span class="text-xs font-semibold uppercase">
                                {{ revision.status }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ revision.comments_count }} reviewer comments
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Submitted drafts and reviewer feedback will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <CircleDollarSign class="h-5 w-5 text-teal-600" />
                    <h2 class="text-base font-semibold">Creator Visibility</h2>
                </div>
                <div v-if="revenue_rules.length" class="space-y-3">
                    <div
                        v-for="rule in revenue_rules"
                        :key="rule.id"
                        class="rounded-md border p-4"
                    >
                        <p class="font-medium">
                            {{ rule.course_title || rule.type }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ rule.share_percent }}% / {{ rule.status }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Approved creators can receive profile exposure on their
                    published blogs and courses. Direct creator payments are not
                    enabled for this platform.
                </p>

                <div class="mt-5 rounded-md bg-muted p-4 text-sm">
                    Profile promotion value:
                    <strong>{{ money(0) }}</strong>
                </div>
            </section>
        </div>
    </div>
</template>
