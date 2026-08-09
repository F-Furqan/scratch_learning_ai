<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BarChart3,
    Bell,
    BookOpenCheck,
    CheckCheck,
    Clock3,
    FileCheck2,
    FilePenLine,
    Link as LinkIcon,
    MessageSquareText,
    RefreshCcw,
    Trash2,
} from '@lucide/vue';

type Profile = {
    name: string;
    blogger_status: string | null;
    instructor_status: string | null;
    instructor_display_name: string | null;
    bio: string | null;
    expertise: string | null;
    linkedin_url: string | null;
    website_url: string | null;
    admin_notes: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    is_verified_expert: boolean;
    badges: { id: number; name: string; color: string }[];
};

type Summary = {
    drafts: number;
    pending_approval: number;
    published: number;
    changes_requested: number;
    delete_requests: number;
    blog_drafts: number;
    course_drafts: number;
    blog_pending: number;
    course_pending: number;
    blog_published: number;
    course_published: number;
};

type Analytics = {
    blog_posts: number;
    courses: number;
    blog_views: number;
    course_views: number;
    lesson_views: number;
    comments_count: number;
    enrollments_count: number;
    engagement_score: number;
};

type ContentCard = {
    id: number;
    type: 'blog' | 'course';
    title: string;
    status: string | null;
    category: string | null;
    engagement_label: string;
    manage_url: string;
    public_url: string | null;
    rejection_reason: string | null;
    admin_notes: string | null;
};

type Activity = {
    id: number;
    decision: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    actor: string | null;
    created_at: string | null;
};

type DeleteRequest = {
    id: number;
    status: string | null;
    reason: string | null;
    admin_note: string | null;
    content_title: string | null;
    content_type: string;
    created_at: string | null;
};

type Revision = {
    id: number;
    title: string;
    status: string | null;
    comments_count: number;
};

type CreatorNotification = {
    id: string;
    event: string;
    severity: string;
    title: string;
    message: string;
    note: string | null;
    content_type: string | null;
    content_id: number | null;
    content_title: string | null;
    status: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

defineProps<{
    profile: Profile;
    agreement: {
        version: string;
        accepted: boolean;
        url: string;
    };
    summary: Summary;
    analytics: Analytics;
    notifications: CreatorNotification[];
    unread_notification_count: number;
    recent_content: ContentCard[];
    approval_activity: Activity[];
    delete_requests: DeleteRequest[];
    revisions: Revision[];
    actions: {
        profile_url: string;
        blogs_url: string;
        courses_url: string;
        create_blog_url: string;
        create_course_url: string;
    };
    capabilities: {
        can_create_blogs: boolean;
        can_create_courses: boolean;
    };
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

const stats = [
    {
        label: 'Drafts',
        key: 'drafts',
        icon: FilePenLine,
        tone: 'text-slate-700',
    },
    {
        label: 'Pending approval',
        key: 'pending_approval',
        icon: Clock3,
        tone: 'text-amber-700',
    },
    {
        label: 'Published',
        key: 'published',
        icon: BadgeCheck,
        tone: 'text-emerald-700',
    },
    {
        label: 'Changes requested',
        key: 'changes_requested',
        icon: RefreshCcw,
        tone: 'text-rose-700',
    },
    {
        label: 'Delete requests',
        key: 'delete_requests',
        icon: Trash2,
        tone: 'text-slate-700',
    },
] as const;

function number(value: number) {
    return new Intl.NumberFormat('en').format(value);
}

function statusClass(status: string | null) {
    if (status === 'published') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (status === 'pending') {
        return 'bg-amber-100 text-amber-900';
    }

    if (status === 'rejected') {
        return 'bg-rose-100 text-rose-800';
    }

    if (status === 'archived') {
        return 'bg-slate-200 text-slate-700';
    }

    return 'bg-slate-100 text-slate-700';
}

function alertClass(alert: CreatorNotification) {
    if (!alert.read_at) {
        return 'border-teal-200 bg-teal-50/70';
    }

    if (alert.severity === 'danger') {
        return 'border-rose-100 bg-rose-50/50';
    }

    if (alert.severity === 'warning') {
        return 'border-amber-100 bg-amber-50/50';
    }

    if (alert.severity === 'success') {
        return 'border-emerald-100 bg-emerald-50/50';
    }

    return 'border-slate-200 bg-card';
}

function markRead(alert: CreatorNotification) {
    router.patch(
        `/creator/notifications/${alert.id}/read`,
        {},
        { preserveScroll: true },
    );
}

function markAllRead() {
    router.patch(
        '/creator/notifications/read-all',
        {},
        { preserveScroll: true },
    );
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
                    Profile, approvals, publishing status, and content
                    engagement.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="actions.profile_url"
                    class="rounded-md border px-4 py-2 text-sm font-semibold"
                >
                    Profile
                </Link>
                <Link
                    v-if="capabilities.can_create_blogs"
                    :href="actions.create_blog_url"
                    class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white"
                >
                    New blog
                </Link>
                <Link
                    v-if="capabilities.can_create_courses"
                    :href="actions.create_course_url"
                    class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white"
                >
                    New course
                </Link>
            </div>
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
                            uploading blogs, courses, or deletion requests.
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

        <section
            v-if="notifications.length"
            class="rounded-lg border bg-card p-5"
        >
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <Bell class="mt-0.5 h-5 w-5 text-teal-700" />
                    <div>
                        <h2 class="text-base font-semibold">
                            Dashboard alerts
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ unread_notification_count }} unread creator
                            updates.
                        </p>
                    </div>
                </div>
                <button
                    v-if="unread_notification_count > 0"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold"
                    @click="markAllRead"
                >
                    <CheckCheck class="h-4 w-4" />
                    Mark all read
                </button>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <article
                    v-for="alert in notifications"
                    :key="alert.id"
                    class="rounded-md border p-4"
                    :class="alertClass(alert)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ alert.title }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ alert.message }}
                            </p>
                        </div>
                        <span
                            v-if="!alert.read_at"
                            class="rounded-md bg-teal-700 px-2 py-1 text-xs font-semibold text-white uppercase"
                        >
                            New
                        </span>
                    </div>
                    <p
                        v-if="alert.note"
                        class="mt-3 rounded-md bg-white/70 p-3 text-sm text-slate-700"
                    >
                        {{ alert.note }}
                    </p>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-3 text-sm font-semibold"
                    >
                        <Link
                            v-if="alert.action_url"
                            :href="alert.action_url"
                            class="text-teal-700"
                        >
                            Open
                        </Link>
                        <button
                            v-if="!alert.read_at"
                            type="button"
                            class="text-slate-700"
                            @click="markRead(alert)"
                        >
                            Mark read
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-3 md:grid-cols-5">
            <section
                v-for="stat in stats"
                :key="stat.key"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex items-center gap-3">
                    <component
                        :is="stat.icon"
                        class="h-5 w-5"
                        :class="stat.tone"
                    />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ number(summary[stat.key]) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ stat.label }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-[0.85fr_1.15fr]">
            <section class="rounded-lg border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">
                            {{
                                profile.instructor_display_name || profile.name
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Blogger: {{ profile.blogger_status || 'none' }} /
                            Instructor:
                            {{ profile.instructor_status || 'none' }}
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
                <p class="mt-4 text-sm leading-7 text-muted-foreground">
                    {{
                        profile.bio ||
                        'Add a bio so students know your expertise.'
                    }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span
                        v-if="profile.expertise"
                        class="rounded-md bg-muted px-2 py-1"
                    >
                        {{ profile.expertise }}
                    </span>
                    <span
                        v-for="badge in profile.badges"
                        :key="badge.id"
                        class="rounded-md bg-muted px-2 py-1"
                    >
                        {{ badge.name }}
                    </span>
                </div>
                <div class="mt-5 grid gap-2 text-sm">
                    <a
                        v-if="profile.linkedin_url"
                        :href="profile.linkedin_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 font-semibold text-teal-700"
                    >
                        <LinkIcon class="h-4 w-4" />
                        LinkedIn profile
                    </a>
                    <p
                        v-if="profile.admin_notes"
                        class="rounded-md bg-muted p-3 text-muted-foreground"
                    >
                        {{ profile.admin_notes }}
                    </p>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <BarChart3 class="h-5 w-5 text-slate-700" />
                    <h2 class="text-base font-semibold">Basic engagement</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.blog_views) }}
                        </p>
                        <p class="text-xs text-muted-foreground">Blog views</p>
                    </div>
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.course_views) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Course views
                        </p>
                    </div>
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.lesson_views) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Lesson views
                        </p>
                    </div>
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.comments_count) }}
                        </p>
                        <p class="text-xs text-muted-foreground">Comments</p>
                    </div>
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.enrollments_count) }}
                        </p>
                        <p class="text-xs text-muted-foreground">Enrollments</p>
                    </div>
                    <div class="rounded-md border p-4">
                        <p class="text-xl font-semibold">
                            {{ number(analytics.engagement_score) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Engagement score
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-lg border bg-card p-5">
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <BookOpenCheck class="h-5 w-5 text-teal-700" />
                        <h2 class="text-base font-semibold">
                            Published content status
                        </h2>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            :href="actions.blogs_url"
                            class="rounded-md border px-3 py-1.5 text-sm font-semibold"
                        >
                            Blogs
                        </Link>
                        <Link
                            :href="actions.courses_url"
                            class="rounded-md border px-3 py-1.5 text-sm font-semibold"
                        >
                            Courses
                        </Link>
                    </div>
                </div>
                <div
                    v-if="recent_content.length"
                    class="divide-y rounded-md border"
                >
                    <div
                        v-for="content in recent_content"
                        :key="`${content.type}-${content.id}`"
                        class="grid gap-2 p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="font-semibold">{{ content.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ content.type }} /
                                    {{ content.category || 'Uncategorized' }} /
                                    {{ content.engagement_label }}
                                </p>
                            </div>
                            <span
                                class="rounded-md px-2 py-1 text-xs font-semibold uppercase"
                                :class="statusClass(content.status)"
                            >
                                {{ content.status || 'draft' }}
                            </span>
                        </div>
                        <p
                            v-if="
                                content.rejection_reason || content.admin_notes
                            "
                            class="rounded-md bg-rose-50 p-3 text-sm text-rose-900"
                        >
                            {{
                                content.rejection_reason || content.admin_notes
                            }}
                        </p>
                        <div class="flex flex-wrap gap-3 text-sm font-semibold">
                            <Link
                                :href="content.manage_url"
                                class="text-teal-700"
                            >
                                Manage
                            </Link>
                            <Link
                                v-if="content.public_url"
                                :href="content.public_url"
                                class="text-slate-700"
                            >
                                View public page
                            </Link>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Drafts, approvals, and published content will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <MessageSquareText class="h-5 w-5 text-indigo-700" />
                    <h2 class="text-base font-semibold">Approval requests</h2>
                </div>
                <div v-if="approval_activity.length" class="space-y-3">
                    <div
                        v-for="activity in approval_activity"
                        :key="activity.id"
                        class="rounded-md border p-4"
                    >
                        <p class="text-sm font-semibold capitalize">
                            {{ activity.decision.replace('_', ' ') }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ activity.from_status || 'new' }} to
                            {{ activity.to_status || 'none' }}
                        </p>
                        <p v-if="activity.note" class="mt-2 text-sm">
                            {{ activity.note }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Review submissions and rejection notes will appear here.
                </p>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <Trash2 class="h-5 w-5 text-slate-700" />
                    <h2 class="text-base font-semibold">Delete requests</h2>
                </div>
                <div v-if="delete_requests.length" class="space-y-3">
                    <div
                        v-for="request in delete_requests"
                        :key="request.id"
                        class="rounded-md border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{
                                        request.content_title ||
                                        'Deleted content'
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ request.content_type }} /
                                    {{ request.status }}
                                </p>
                            </div>
                            <span
                                class="rounded-md px-2 py-1 text-xs font-semibold uppercase"
                                :class="statusClass(request.status)"
                            >
                                {{ request.status }}
                            </span>
                        </div>
                        <p v-if="request.reason" class="mt-2 text-sm">
                            {{ request.reason }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Published content deletion requests will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <FilePenLine class="h-5 w-5 text-indigo-700" />
                    <h2 class="text-base font-semibold">Editorial revisions</h2>
                </div>
                <div v-if="revisions.length" class="space-y-3">
                    <div
                        v-for="revision in revisions"
                        :key="revision.id"
                        class="rounded-md border p-4"
                    >
                        <p class="font-medium">{{ revision.title }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ revision.status }} /
                            {{ revision.comments_count }}
                            reviewer comments
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Reviewer comments and revision activity will appear here.
                </p>
            </section>
        </div>
    </div>
</template>
