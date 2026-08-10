<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDot,
    MessageSquarePlus,
    RotateCcw,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type Field = {
    path: string;
    label: string;
    current: string;
    proposed: string;
    changed: boolean;
    is_rich_text: boolean;
};

type Comment = {
    id: number;
    field_path: string | null;
    body: string;
    reviewer: string | null;
    is_resolved: boolean;
    resolved_by: string | null;
    created_at: string | null;
    resolve_url: string;
};

const props = defineProps<{
    revision: {
        id: number;
        title: string;
        summary: string | null;
        status: string;
        content_type: string;
        content_title: string | null;
        author: string | null;
        reviewer: string | null;
        submitted_at: string | null;
    };
    fields: Field[];
    comments: Comment[];
    history: Array<Record<string, string | null>>;
    actions: Array<{ label: string; url: string; tone: string }>;
    urls: { index: string; comments: string };
}>();

const selectedField = ref<string | null>(null);
const decisionNote = ref('');
const commentForm = useForm({ field_path: null as string | null, body: '' });
const changedCount = computed(
    () => props.fields.filter((field) => field.changed).length,
);

function addComment(path: string | null = null) {
    selectedField.value = path;
    commentForm.field_path = path;
    window.setTimeout(
        () => document.getElementById('review-comment')?.focus(),
        0,
    );
}

function submitComment() {
    commentForm.post(props.urls.comments, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            selectedField.value = null;
        },
    });
}

function toggleComment(comment: Comment) {
    router.patch(comment.resolve_url, {}, { preserveScroll: true });
}

function runAction(action: { label: string; url: string }) {
    if (
        (action.label.includes('Reject') || action.label.includes('Changes')) &&
        !decisionNote.value.trim()
    ) {
        window.alert('Add a reviewer note before this decision.');

        return;
    }

    router.post(
        action.url,
        { note: decisionNote.value },
        { preserveScroll: true },
    );
}

function actionClass(tone: string) {
    if (tone === 'success') {
        return 'bg-emerald-700 text-white hover:bg-emerald-800';
    }

    if (tone === 'warning') {
        return 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100';
    }

    return 'border-rose-300 bg-rose-50 text-rose-800 hover:bg-rose-100';
}
</script>

<template>
    <Head :title="`Review ${revision.title}`" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Link
                    :href="urls.index"
                    class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                    title="Back to revisions"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-normal">
                            {{ revision.title }}
                        </h1>
                        <span
                            class="rounded-md border px-2 py-1 text-xs font-semibold"
                            >{{ revision.status }}</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ revision.content_type }} ·
                        {{ revision.content_title || 'Unlinked content' }} ·
                        {{ changedCount }} changed fields
                    </p>
                </div>
            </div>
        </header>

        <section
            class="grid gap-3 border-y bg-muted/20 px-4 py-3 text-sm sm:grid-cols-4"
        >
            <div>
                <span class="block text-xs text-muted-foreground">Author</span
                >{{ revision.author || 'Unknown' }}
            </div>
            <div>
                <span class="block text-xs text-muted-foreground">Reviewer</span
                >{{ revision.reviewer || 'Unassigned' }}
            </div>
            <div>
                <span class="block text-xs text-muted-foreground"
                    >Submitted</span
                >{{ revision.submitted_at || 'Not submitted' }}
            </div>
            <div>
                <span class="block text-xs text-muted-foreground">Summary</span
                >{{ revision.summary || 'No summary' }}
            </div>
        </section>

        <div class="grid min-h-0 gap-5 2xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="overflow-hidden rounded-lg border bg-card">
                <div
                    class="hidden grid-cols-[180px_minmax(0,1fr)_minmax(0,1fr)_44px] border-b bg-muted/30 px-3 py-2 text-xs font-semibold text-muted-foreground uppercase md:grid"
                >
                    <span>Field</span><span>Current</span><span>Proposed</span
                    ><span></span>
                </div>
                <article
                    v-for="field in fields"
                    :key="field.path"
                    class="grid grid-cols-1 gap-2 border-b px-3 py-3 last:border-b-0 md:grid-cols-[180px_minmax(0,1fr)_minmax(0,1fr)_44px]"
                    :class="field.changed ? 'bg-amber-50/40' : ''"
                >
                    <div class="flex items-start gap-2 text-sm font-semibold">
                        <CircleDot
                            class="mt-0.5 size-4 shrink-0"
                            :class="
                                field.changed
                                    ? 'text-amber-700'
                                    : 'text-muted-foreground'
                            "
                        />
                        <span>{{ field.label }}</span>
                    </div>
                    <div class="min-w-0">
                        <span
                            class="mb-1 block text-xs font-semibold text-muted-foreground uppercase md:hidden"
                            >Current</span
                        >
                        <pre
                            class="max-h-72 overflow-auto rounded-md border bg-background p-3 text-xs leading-5 whitespace-pre-wrap"
                            >{{ field.current || 'Empty' }}</pre>
                    </div>
                    <div class="min-w-0">
                        <span
                            class="mb-1 block text-xs font-semibold text-muted-foreground uppercase md:hidden"
                            >Proposed</span
                        >
                        <pre
                            class="max-h-72 overflow-auto rounded-md border p-3 text-xs leading-5 whitespace-pre-wrap"
                            :class="
                                field.changed
                                    ? 'border-amber-200 bg-amber-50'
                                    : 'bg-background'
                            "
                            >{{ field.proposed || 'Empty' }}</pre>
                    </div>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                        title="Comment on field"
                        @click="addComment(field.path)"
                    >
                        <MessageSquarePlus class="size-4" />
                    </button>
                </article>
                <p
                    v-if="fields.length === 0"
                    class="px-4 py-12 text-center text-sm text-muted-foreground"
                >
                    No comparable payload fields were found.
                </p>
            </section>

            <aside class="grid h-fit gap-5 2xl:sticky 2xl:top-5">
                <section class="rounded-lg border bg-card p-4">
                    <h2 class="font-semibold">Reviewer comments</h2>
                    <form
                        class="mt-3 grid gap-2"
                        @submit.prevent="submitComment"
                    >
                        <p
                            v-if="selectedField"
                            class="text-xs font-semibold text-primary"
                        >
                            Field: {{ selectedField }}
                        </p>
                        <textarea
                            id="review-comment"
                            v-model="commentForm.body"
                            required
                            class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Add a precise review note"
                        />
                        <span
                            v-if="commentForm.errors.body"
                            class="text-xs text-destructive"
                            >{{ commentForm.errors.body }}</span
                        >
                        <div class="flex justify-between gap-2">
                            <button
                                v-if="selectedField"
                                type="button"
                                class="text-xs font-medium text-muted-foreground"
                                @click="addComment(null)"
                            >
                                Clear field
                            </button>
                            <span v-else></span>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                                :disabled="commentForm.processing"
                            >
                                Add comment
                            </button>
                        </div>
                    </form>
                    <div class="mt-4 grid gap-3 border-t pt-4">
                        <article
                            v-for="comment in comments"
                            :key="comment.id"
                            class="rounded-md border p-3"
                            :class="
                                comment.is_resolved
                                    ? 'bg-muted/30 opacity-70'
                                    : 'bg-background'
                            "
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs font-semibold">
                                        {{
                                            comment.field_path ||
                                            'General review'
                                        }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6">
                                        {{ comment.body }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="grid size-8 shrink-0 place-items-center rounded-md border"
                                    :title="
                                        comment.is_resolved
                                            ? 'Reopen comment'
                                            : 'Resolve comment'
                                    "
                                    @click="toggleComment(comment)"
                                >
                                    <RotateCcw
                                        v-if="comment.is_resolved"
                                        class="size-4"
                                    />
                                    <CheckCircle2 v-else class="size-4" />
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ comment.reviewer || 'Reviewer' }} ·
                                {{ comment.created_at }}
                            </p>
                        </article>
                        <p
                            v-if="comments.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No reviewer comments yet.
                        </p>
                    </div>
                </section>

                <section
                    v-if="actions.length"
                    class="rounded-lg border bg-card p-4"
                >
                    <h2 class="font-semibold">Decision</h2>
                    <textarea
                        v-model="decisionNote"
                        class="mt-3 min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Decision note"
                    />
                    <div class="mt-3 grid gap-2">
                        <button
                            v-for="action in actions"
                            :key="action.url"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-semibold"
                            :class="actionClass(action.tone)"
                            @click="runAction(action)"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
