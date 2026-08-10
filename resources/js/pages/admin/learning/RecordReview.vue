<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    History,
    Save,
    ShieldCheck,
} from '@lucide/vue';

type Option = { label: string; value: string };
type Field = {
    name: string;
    label: string;
    type: 'number' | 'select' | 'text' | 'textarea';
    options: Option[];
    required: boolean;
    min: number | null;
    max: number | null;
};

type FormValue = string | number | null;

const props = defineProps<{
    record: {
        id: number;
        type: string;
        eyebrow: string;
        title: string;
        subtitle: string;
        status: string;
    };
    details: Array<{ label: string; value: string | null }>;
    evidence: Array<{
        label: string;
        value: string;
        format: 'json' | 'link' | 'text';
    }>;
    fields: Field[];
    values: Record<string, FormValue>;
    history: Array<{
        id: number;
        action: string;
        actor: string | null;
        reason: string | null;
        from_status: string | null;
        to_status: string | null;
        created_at: string | null;
    }>;
    urls: { index: string; update: string };
}>();

const form = useForm<Record<string, FormValue>>({ ...props.values });

function submit() {
    form.patch(props.urls.update, {
        preserveScroll: true,
    });
}

function statusLabel(value: string | null) {
    return value ? value.replaceAll('_', ' ') : 'Not recorded';
}
</script>

<template>
    <Head :title="record.title" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <Link
                    :href="urls.index"
                    class="grid size-9 shrink-0 place-items-center rounded-md border hover:bg-muted"
                    title="Back to learning records"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        {{ record.eyebrow }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-normal">
                            {{ record.title }}
                        </h1>
                        <span
                            class="rounded-md border px-2 py-1 text-xs font-semibold capitalize"
                        >
                            {{ statusLabel(record.status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ record.subtitle }}
                    </p>
                </div>
            </div>
        </header>

        <section
            class="grid gap-3 border-y bg-muted/20 px-4 py-3 text-sm sm:grid-cols-2 xl:grid-cols-4"
        >
            <div v-for="detail in details" :key="detail.label">
                <span class="block text-xs text-muted-foreground">
                    {{ detail.label }}
                </span>
                <span class="break-words">{{ detail.value || '—' }}</span>
            </div>
        </section>

        <div class="grid min-h-0 gap-5 2xl:grid-cols-[minmax(0,1fr)_400px]">
            <div class="grid content-start gap-5">
                <section
                    v-if="evidence.length"
                    class="overflow-hidden rounded-lg border bg-card"
                >
                    <div class="border-b bg-muted/30 px-4 py-3">
                        <h2 class="font-semibold">Submitted evidence</h2>
                    </div>
                    <div class="grid gap-4 p-4">
                        <article
                            v-for="item in evidence"
                            :key="item.label"
                            class="min-w-0"
                        >
                            <h3
                                class="mb-2 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                {{ item.label }}
                            </h3>
                            <a
                                v-if="item.format === 'link'"
                                :href="item.value"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                            >
                                {{ item.value }}
                                <ExternalLink class="size-4" />
                            </a>
                            <pre
                                v-else-if="item.format === 'json'"
                                class="max-h-96 overflow-auto rounded-md bg-zinc-950 p-4 text-xs leading-5 whitespace-pre-wrap text-zinc-100"
                                >{{ item.value }}</pre>
                            <div
                                v-else
                                class="rounded-md border bg-background p-4 text-sm leading-6 whitespace-pre-wrap"
                            >
                                {{ item.value }}
                            </div>
                        </article>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border bg-card">
                    <div
                        class="flex items-center gap-2 border-b bg-muted/30 px-4 py-3"
                    >
                        <History class="size-4" />
                        <h2 class="font-semibold">Change history</h2>
                    </div>
                    <div v-if="history.length" class="divide-y">
                        <article
                            v-for="entry in history"
                            :key="entry.id"
                            class="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div>
                                <p class="font-semibold">{{ entry.action }}</p>
                                <p
                                    v-if="entry.reason"
                                    class="mt-1 text-muted-foreground"
                                >
                                    {{ entry.reason }}
                                </p>
                                <p
                                    v-if="entry.from_status || entry.to_status"
                                    class="mt-1 text-xs text-muted-foreground capitalize"
                                >
                                    {{ statusLabel(entry.from_status) }} →
                                    {{ statusLabel(entry.to_status) }}
                                </p>
                            </div>
                            <div
                                class="text-xs text-muted-foreground sm:text-right"
                            >
                                <p>{{ entry.actor || 'System' }}</p>
                                <p>{{ entry.created_at }}</p>
                            </div>
                        </article>
                    </div>
                    <p
                        v-else
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No controlled changes recorded.
                    </p>
                </section>
            </div>

            <aside class="h-fit rounded-lg border bg-card 2xl:sticky 2xl:top-5">
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <ShieldCheck class="size-4" />
                    <h2 class="font-semibold">Controlled action</h2>
                </div>
                <form class="grid gap-4 p-4" @submit.prevent="submit">
                    <label
                        v-for="field in fields"
                        :key="field.name"
                        class="grid gap-1.5 text-sm font-medium"
                    >
                        <span>{{ field.label }}</span>
                        <select
                            v-if="field.type === 'select'"
                            v-model="form[field.name]"
                            :required="field.required"
                            class="h-10 rounded-md border bg-background px-3 font-normal"
                        >
                            <option
                                v-for="option in field.options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <textarea
                            v-else-if="field.type === 'textarea'"
                            v-model="form[field.name]"
                            :required="field.required"
                            class="min-h-28 rounded-md border bg-background px-3 py-2 font-normal"
                        />
                        <input
                            v-else
                            v-model="form[field.name]"
                            :type="field.type"
                            :required="field.required"
                            :min="field.min ?? undefined"
                            :max="field.max ?? undefined"
                            class="h-10 rounded-md border bg-background px-3 font-normal"
                        />
                        <span
                            v-if="form.errors[field.name]"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors[field.name] }}
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        Save change
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
