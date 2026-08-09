<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock3,
    FilePenLine,
    GitCompareArrows,
    History,
    ListChecks,
    ShieldCheck,
} from '@lucide/vue';
import { computed, reactive } from 'vue';

type ReviewAction = {
    label: string;
    url: string;
    tone: 'success' | 'warning' | 'danger' | string;
    method: 'post' | 'delete';
};

type ReviewMeta = {
    label: string;
    value: string | null;
    url: string | null;
};

type ReviewHistory = {
    decision: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    actor: string | null;
    created_at: string | null;
    checklist?: {
        label: string;
        checked: boolean;
    }[];
};

type RevisionDiff = {
    label: string;
    from: string | null;
    to: string | null;
};

type ReviewItem = {
    key: string;
    id: number;
    type: string;
    title: string;
    subtitle: string | null;
    status: string | null;
    submitted_at: string | null;
    meta: ReviewMeta[];
    history: ReviewHistory[];
    diff?: RevisionDiff[];
    actions: ReviewAction[];
};

type ReviewSection = {
    key: string;
    title: string;
    description: string;
    count: number;
    items: ReviewItem[];
};

const props = defineProps<{
    summary: {
        total_pending: number;
        sections: number;
    };
    sections: ReviewSection[];
}>();

const notes = reactive<Record<string, string>>({});
const checklists = reactive<Record<string, Record<string, boolean>>>({});
const reviewChecklistItems = [
    {
        key: 'ownership_rights',
        label: 'Rights and ownership checked',
    },
    {
        key: 'editorial_quality',
        label: 'Editorial quality checked',
    },
    {
        key: 'seo_metadata',
        label: 'SEO metadata checked',
    },
    {
        key: 'policy_safety',
        label: 'Policy and safety checked',
    },
];

const activeSections = computed(() =>
    props.sections.filter((section) => section.items.length > 0),
);

function noteFor(item: ReviewItem) {
    return notes[item.key] || '';
}

function setNote(item: ReviewItem, value: string) {
    notes[item.key] = value;
}

function checklistFor(item: ReviewItem) {
    return checklists[item.key] || {};
}

function setChecklist(item: ReviewItem, key: string, checked: boolean) {
    checklists[item.key] = {
        ...checklistFor(item),
        [key]: checked,
    };
}

function runAction(item: ReviewItem, action: ReviewAction) {
    const payload = {
        note: noteFor(item) || null,
        review_checklist: checklistFor(item),
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            notes[item.key] = '';
            checklists[item.key] = {};
        },
    };

    if (action.method === 'delete') {
        router.delete(action.url, {
            ...options,
            data: payload,
        });

        return;
    }

    router.post(action.url, payload, options);
}

function actionClass(action: ReviewAction) {
    if (action.tone === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100';
    }

    if (action.tone === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100';
    }

    if (action.tone === 'danger') {
        return 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100';
    }

    return 'border hover:bg-muted';
}

function statusLabel(status: string | null) {
    return status ? status.replaceAll('_', ' ') : 'unknown';
}

function compactMeta(item: ReviewItem) {
    return item.meta.filter((meta) => meta.value);
}

function compactDiff(item: ReviewItem) {
    return item.diff || [];
}

function textareaValue(event: Event) {
    return (event.target as HTMLTextAreaElement).value;
}

function checkboxValue(event: Event) {
    return (event.target as HTMLInputElement).checked;
}
</script>

<template>
    <Head title="Review Center" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-muted-foreground">
                    Admin Operations
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal">
                    Review Center
                </h1>
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-md border bg-card px-3 py-2">
                    <p class="text-muted-foreground">Pending</p>
                    <p class="text-xl font-semibold">
                        {{ summary.total_pending }}
                    </p>
                </div>
                <div class="rounded-md border bg-card px-3 py-2">
                    <p class="text-muted-foreground">Queues</p>
                    <p class="text-xl font-semibold">{{ summary.sections }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[260px_1fr]">
            <aside class="h-max rounded-lg border bg-card p-3">
                <div
                    class="flex items-center gap-2 px-2 py-1 text-sm font-semibold"
                >
                    <ShieldCheck class="size-4 text-muted-foreground" />
                    Queues
                </div>
                <nav class="mt-2 grid gap-1">
                    <a
                        v-for="section in sections"
                        :key="section.key"
                        :href="`#${section.key}`"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-2 text-sm hover:bg-muted"
                    >
                        <span>{{ section.title }}</span>
                        <span class="font-semibold">{{ section.count }}</span>
                    </a>
                </nav>
            </aside>

            <main class="grid gap-5">
                <section
                    v-for="section in sections"
                    :id="section.key"
                    :key="section.key"
                    class="rounded-lg border bg-card"
                >
                    <div
                        class="flex flex-col gap-2 border-b p-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                {{ section.title }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ section.description }}
                            </p>
                        </div>
                        <div
                            class="inline-flex w-max items-center gap-2 rounded-md border px-2.5 py-1 text-sm font-semibold"
                        >
                            <Clock3 class="size-4 text-muted-foreground" />
                            {{ section.count }}
                        </div>
                    </div>

                    <div v-if="section.items.length" class="divide-y">
                        <article
                            v-for="item in section.items"
                            :key="item.key"
                            class="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_340px]"
                        >
                            <div class="grid gap-3">
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs font-semibold text-muted-foreground"
                                        >
                                            <span>{{ item.type }}</span>
                                            <span
                                                class="rounded-md border px-2 py-0.5 capitalize"
                                            >
                                                {{ statusLabel(item.status) }}
                                            </span>
                                        </div>
                                        <h3
                                            class="mt-1 text-base font-semibold tracking-normal"
                                        >
                                            {{ item.title }}
                                        </h3>
                                        <p
                                            v-if="item.subtitle"
                                            class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                                        >
                                            {{ item.subtitle }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="item.submitted_at"
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{ item.submitted_at }}
                                    </p>
                                </div>

                                <dl class="grid gap-2 md:grid-cols-2">
                                    <div
                                        v-for="meta in compactMeta(item)"
                                        :key="meta.label"
                                        class="min-w-0 rounded-md border px-3 py-2 text-sm"
                                    >
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ meta.label }}
                                        </dt>
                                        <dd class="mt-1 truncate font-medium">
                                            <a
                                                v-if="meta.url"
                                                :href="meta.url"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="text-primary hover:underline"
                                            >
                                                {{ meta.value }}
                                            </a>
                                            <span v-else>{{ meta.value }}</span>
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    v-if="compactDiff(item).length"
                                    class="grid gap-2"
                                >
                                    <div
                                        class="flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                                    >
                                        <GitCompareArrows class="size-4" />
                                        Revision Preview
                                    </div>
                                    <div class="grid gap-2">
                                        <div
                                            v-for="diff in compactDiff(item)"
                                            :key="`${item.key}-${diff.label}`"
                                            class="grid gap-2 rounded-md border px-3 py-2 text-xs md:grid-cols-[9rem_1fr_1fr]"
                                        >
                                            <div class="font-semibold">
                                                {{ diff.label }}
                                            </div>
                                            <div class="min-w-0">
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    Current
                                                </span>
                                                <p class="mt-1 break-words">
                                                    {{ diff.from || 'Empty' }}
                                                </p>
                                            </div>
                                            <div class="min-w-0">
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    Proposed
                                                </span>
                                                <p class="mt-1 break-words">
                                                    {{ diff.to || 'Empty' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="item.history.length"
                                    class="grid gap-2"
                                >
                                    <div
                                        class="flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                                    >
                                        <History class="size-4" />
                                        Recent Decisions
                                    </div>
                                    <div class="grid gap-2">
                                        <div
                                            v-for="history in item.history"
                                            :key="`${history.decision}-${history.created_at}`"
                                            class="rounded-md border px-3 py-2 text-xs"
                                        >
                                            <div
                                                class="flex flex-wrap justify-between gap-2 font-semibold"
                                            >
                                                <span class="capitalize">
                                                    {{
                                                        history.decision.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    {{ history.created_at }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-1 text-muted-foreground"
                                            >
                                                {{
                                                    history.from_status ||
                                                    'none'
                                                }}
                                                to
                                                {{
                                                    history.to_status || 'none'
                                                }}
                                                by
                                                {{ history.actor || 'System' }}
                                            </p>
                                            <p v-if="history.note" class="mt-1">
                                                {{ history.note }}
                                            </p>
                                            <div
                                                v-if="history.checklist?.length"
                                                class="mt-2 flex flex-wrap gap-1.5"
                                            >
                                                <span
                                                    v-for="entry in history.checklist"
                                                    :key="entry.label"
                                                    class="rounded-md border px-2 py-0.5"
                                                    :class="
                                                        entry.checked
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                            : 'border-slate-200 bg-slate-50 text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        entry.checked
                                                            ? 'Yes'
                                                            : 'No'
                                                    }}
                                                    {{ entry.label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form
                                class="grid content-start gap-3"
                                @submit.prevent
                            >
                                <label class="grid gap-1 text-sm font-medium">
                                    <span>Decision Note</span>
                                    <textarea
                                        :value="noteFor(item)"
                                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                                        @input="
                                            setNote(item, textareaValue($event))
                                        "
                                    ></textarea>
                                </label>

                                <div class="grid gap-2 rounded-md border p-3">
                                    <div
                                        class="flex items-center gap-2 text-sm font-semibold"
                                    >
                                        <ListChecks
                                            class="size-4 text-muted-foreground"
                                        />
                                        Reviewer checklist
                                    </div>
                                    <label
                                        v-for="entry in reviewChecklistItems"
                                        :key="`${item.key}-${entry.key}`"
                                        class="flex items-start gap-2 text-xs font-medium text-muted-foreground"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 h-4 w-4 rounded border-input"
                                            :checked="
                                                checklistFor(item)[entry.key] ||
                                                false
                                            "
                                            @change="
                                                setChecklist(
                                                    item,
                                                    entry.key,
                                                    checkboxValue($event),
                                                )
                                            "
                                        />
                                        <span>{{ entry.label }}</span>
                                    </label>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="action in item.actions"
                                        :key="action.url"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-semibold"
                                        :class="actionClass(action)"
                                        @click="runAction(item, action)"
                                    >
                                        <CheckCircle2 class="size-4" />
                                        {{ action.label }}
                                    </button>
                                </div>
                            </form>
                        </article>
                    </div>

                    <div
                        v-else
                        class="flex items-center gap-3 p-6 text-sm text-muted-foreground"
                    >
                        <FilePenLine class="size-5" />
                        No pending reviews
                    </div>
                </section>

                <section
                    v-if="activeSections.length === 0"
                    class="rounded-lg border bg-card p-8 text-center"
                >
                    <ShieldCheck class="mx-auto size-8 text-muted-foreground" />
                    <h2 class="mt-3 text-base font-semibold">
                        Review queues are clear
                    </h2>
                </section>
            </main>
        </div>
    </div>
</template>
