<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    Clock3,
    FilePenLine,
    RefreshCcw,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';

type ContentItem = {
    id: number;
    title: string;
    category: string | null;
    status: string | null;
    published_at: string | null;
    updated_at: string | null;
    rejection_reason: string | null;
    admin_notes: string | null;
    engagement_label: string;
    edit_url: string | null;
    submit_url: string | null;
    delete_request_url: string | null;
    public_url: string | null;
    active_revision: {
        id: number;
        status: string | null;
        title: string;
        review_note: string | null;
        submitted_at: string | null;
        reviewed_at: string | null;
        latest_approval: {
            decision: string;
            note: string | null;
            actor: string | null;
            created_at: string | null;
        } | null;
    } | null;
    pending_delete_request: {
        id: number;
        reason: string | null;
        status: string | null;
    } | null;
    latest_approval: {
        decision: string;
        from_status: string | null;
        to_status: string | null;
        note: string | null;
        actor: string | null;
        created_at: string | null;
    } | null;
};

type StatusOption = {
    label: string;
    value: string;
};

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

type Summary = {
    drafts: number;
    pending_approval: number;
    published: number;
    changes_requested: number;
    delete_requests: number;
};

const props = defineProps<{
    kind: 'blog' | 'course';
    title: string;
    description: string;
    create_url: string;
    filters: { status: string };
    status_options: StatusOption[];
    summary: Summary;
    items: Paginator<ContentItem>;
    can_create: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator content',
                href: '/creator/dashboard',
            },
        ],
    },
});

const filterLinks = computed(() => [
    { label: 'All', value: '' },
    ...props.status_options,
]);

const stats = [
    { label: 'Drafts', key: 'drafts', icon: FilePenLine },
    { label: 'Pending', key: 'pending_approval', icon: Clock3 },
    { label: 'Published', key: 'published', icon: BookOpenCheck },
    { label: 'Changes', key: 'changes_requested', icon: RefreshCcw },
    { label: 'Delete requests', key: 'delete_requests', icon: Trash2 },
] as const;

function filterUrl(value: string) {
    const path = props.kind === 'blog' ? '/creator/blogs' : '/creator/courses';

    return value ? `${path}?status=${value}` : path;
}

function statusClass(status: string | null) {
    if (status === 'published') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (
        status === 'pending' ||
        status === 'submitted' ||
        status === 'approved'
    ) {
        return 'bg-amber-100 text-amber-900';
    }

    if (status === 'rejected' || status === 'changes_requested') {
        return 'bg-rose-100 text-rose-800';
    }

    if (status === 'delete_requested') {
        return 'bg-orange-100 text-orange-900';
    }

    if (status === 'archived' || status === 'trashed') {
        return 'bg-slate-200 text-slate-700';
    }

    return 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    {{ title }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <Link
                v-if="can_create"
                :href="create_url"
                class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white"
            >
                New {{ kind }}
            </Link>
        </div>

        <div class="grid gap-3 md:grid-cols-5">
            <section
                v-for="stat in stats"
                :key="stat.key"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex items-center gap-3">
                    <component :is="stat.icon" class="h-5 w-5 text-slate-700" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ summary[stat.key] }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ stat.label }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                v-for="filter in filterLinks"
                :key="filter.value"
                :href="filterUrl(filter.value)"
                class="rounded-md border px-3 py-1.5 text-sm font-semibold"
                :class="
                    filters.status === filter.value
                        ? 'bg-slate-950 text-white'
                        : 'bg-card text-foreground'
                "
            >
                {{ filter.label }}
            </Link>
        </div>

        <section class="rounded-lg border bg-card">
            <div v-if="items.data.length" class="divide-y">
                <article
                    v-for="item in items.data"
                    :key="item.id"
                    class="grid gap-4 p-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-semibold">{{ item.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ item.category || 'Uncategorized' }} /
                                {{ item.engagement_label }}
                            </p>
                        </div>
                        <span
                            class="rounded-md px-2 py-1 text-xs font-semibold uppercase"
                            :class="statusClass(item.status)"
                        >
                            {{ item.status || 'draft' }}
                        </span>
                    </div>

                    <div
                        v-if="item.rejection_reason || item.admin_notes"
                        class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-950"
                    >
                        <p class="font-semibold">Reviewer notes</p>
                        <p class="mt-1">
                            {{ item.rejection_reason || item.admin_notes }}
                        </p>
                    </div>

                    <div
                        v-if="item.latest_approval"
                        class="rounded-md bg-muted p-4 text-sm"
                    >
                        <p class="font-semibold capitalize">
                            {{
                                item.latest_approval.decision.replace('_', ' ')
                            }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{ item.latest_approval.from_status || 'new' }} to
                            {{ item.latest_approval.to_status || 'none' }}
                        </p>
                        <p v-if="item.latest_approval.note" class="mt-2">
                            {{ item.latest_approval.note }}
                        </p>
                    </div>

                    <div
                        v-if="item.active_revision"
                        class="rounded-md border border-teal-200 bg-teal-50 p-4 text-sm text-teal-950"
                    >
                        <p class="font-semibold">
                            Revision
                            {{ item.active_revision.status || 'submitted' }}
                        </p>
                        <p class="mt-1 text-teal-900">
                            {{ item.active_revision.title }}
                        </p>
                        <p
                            v-if="item.active_revision.review_note"
                            class="mt-2 text-teal-900"
                        >
                            {{ item.active_revision.review_note }}
                        </p>
                    </div>

                    <div
                        v-if="item.pending_delete_request"
                        class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                    >
                        Delete request pending:
                        {{
                            item.pending_delete_request.reason ||
                            'No reason provided.'
                        }}
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            v-if="item.edit_url"
                            :href="item.edit_url"
                            class="rounded-md border px-3 py-1.5 text-sm font-semibold"
                        >
                            Edit
                        </Link>

                        <Form
                            v-if="item.submit_url"
                            :action="item.submit_url"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                            >
                                Submit for approval
                            </Button>
                        </Form>

                        <Form
                            v-if="
                                item.delete_request_url &&
                                !item.pending_delete_request
                            "
                            :action="item.delete_request_url"
                            method="post"
                            v-slot="{ processing }"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <input
                                type="hidden"
                                name="reason"
                                value="Creator requested deletion from dashboard."
                            />
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                :disabled="processing"
                            >
                                Request delete
                            </Button>
                        </Form>

                        <Link
                            v-if="item.public_url"
                            :href="item.public_url"
                            class="text-sm font-semibold text-teal-700"
                        >
                            Public page
                        </Link>
                    </div>
                </article>
            </div>
            <p v-else class="p-6 text-sm text-muted-foreground">
                No {{ kind }} records match this view.
            </p>
        </section>

        <div v-if="items.links.length > 3" class="flex flex-wrap gap-2">
            <Link
                v-for="link in items.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-slate-950 text-white' : 'bg-card'"
            >
                {{ link.label }}
            </Link>
        </div>
    </div>
</template>
