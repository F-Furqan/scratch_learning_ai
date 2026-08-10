<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    DatabaseBackup,
    HeartPulse,
    Radar,
} from '@lucide/vue';
import { computed } from 'vue';

type StatusCard = {
    label: string;
    value: string;
    tone: string;
    detail: string | null;
    href: string;
};

type HealthRow = {
    name: string;
    status: string;
    checkedAt: string | null;
    details: Record<string, unknown>;
};

type AlertRow = {
    id: number;
    title: string;
    severity: string;
    status: string;
    source: string;
    lastDetectedAt: string | null;
};

const props = defineProps<{
    snapshot: {
        status: string;
        generatedAt: string;
        cards: StatusCard[];
        health: HealthRow[];
        recentAlerts: AlertRow[];
        counts: {
            errors24h: number;
            unhealthyChecks24h: number;
            verifiedBackups: number;
        };
    };
    capabilities: {
        backup: boolean;
        health: boolean;
        monitor: boolean;
        backupsEnabled: boolean;
    };
}>();

const actionForm = useForm({
    note: '',
    verify: true,
});

const statusLabel = computed(() =>
    props.snapshot.status === 'operational' ? 'Operational' : 'Needs attention',
);

function runAction(action: 'backup' | 'health' | 'monitor') {
    actionForm.post(`/admin/operations/actions/${action}`, {
        preserveScroll: true,
        onSuccess: () => actionForm.reset('note'),
    });
}

function toneClass(tone: string) {
    if (tone === 'healthy') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    if (tone === 'critical') {
        return 'border-rose-200 bg-rose-50 text-rose-900';
    }

    return 'border-amber-200 bg-amber-50 text-amber-950';
}

function dateTime(value: string | null) {
    return value ? new Date(value).toLocaleString() : 'Not recorded';
}

function details(value: Record<string, unknown>) {
    const entries = Object.entries(value);

    return entries.length
        ? entries
              .slice(0, 4)
              .map(([key, item]) => `${key}: ${String(item)}`)
              .join(' · ')
        : 'No additional details';
}
</script>

<template>
    <Head title="Operations Center" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header
            class="flex flex-col gap-3 border-b pb-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <div class="flex items-center gap-2 text-sm font-medium">
                    <span
                        class="size-2 rounded-full"
                        :class="
                            snapshot.status === 'operational'
                                ? 'bg-emerald-500'
                                : 'bg-amber-500'
                        "
                    ></span>
                    {{ statusLabel }}
                </div>
                <h1 class="mt-2 text-2xl font-semibold tracking-normal">
                    Operations Center
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Last refreshed {{ dateTime(snapshot.generatedAt) }}
                </p>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center text-sm">
                <div class="border-l px-3">
                    <p class="font-semibold">{{ snapshot.counts.errors24h }}</p>
                    <p class="text-xs text-muted-foreground">Errors 24h</p>
                </div>
                <div class="border-l px-3">
                    <p class="font-semibold">
                        {{ snapshot.counts.unhealthyChecks24h }}
                    </p>
                    <p class="text-xs text-muted-foreground">Failed checks</p>
                </div>
                <div class="border-x px-3">
                    <p class="font-semibold">
                        {{ snapshot.counts.verifiedBackups }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Verified backups
                    </p>
                </div>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="card in snapshot.cards"
                :key="card.label"
                :href="card.href"
                class="rounded-lg border p-4 transition-colors hover:bg-muted/40"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="mt-2 text-xl font-semibold">
                            {{ card.value }}
                        </p>
                    </div>
                    <span
                        class="rounded-md border px-2 py-1 text-xs font-semibold"
                        :class="toneClass(card.tone)"
                    >
                        {{ card.tone }}
                    </span>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    {{ card.detail || 'No recent activity' }}
                </p>
            </Link>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <div class="flex items-center gap-2">
                <Activity class="size-4 text-muted-foreground" />
                <h2 class="font-semibold">Controlled actions</h2>
            </div>
            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto_auto_auto]">
                <div>
                    <input
                        v-model="actionForm.note"
                        type="text"
                        maxlength="1000"
                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        placeholder="Operational reason"
                    />
                    <p
                        v-if="actionForm.errors.note"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ actionForm.errors.note }}
                    </p>
                </div>
                <button
                    v-if="capabilities.health"
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50"
                    :disabled="actionForm.processing || !actionForm.note"
                    title="Run application, database, queue, and storage checks"
                    @click="runAction('health')"
                >
                    <HeartPulse class="size-4" />
                    Run health
                </button>
                <button
                    v-if="capabilities.monitor"
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50"
                    :disabled="actionForm.processing || !actionForm.note"
                    title="Inspect queue, Paddle, and backup alerts"
                    @click="runAction('monitor')"
                >
                    <Radar class="size-4" />
                    Run monitor
                </button>
                <button
                    v-if="capabilities.backup"
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50"
                    :disabled="
                        actionForm.processing ||
                        !actionForm.note ||
                        !capabilities.backupsEnabled
                    "
                    title="Queue a backup with disposable restore verification"
                    @click="runAction('backup')"
                >
                    <DatabaseBackup class="size-4" />
                    Backup + verify
                </button>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1.25fr_1fr]">
            <section class="overflow-hidden rounded-lg border bg-card">
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <HeartPulse class="size-4 text-muted-foreground" />
                        <h2 class="font-semibold">Latest health checks</h2>
                    </div>
                    <Link
                        href="/admin/operations/health"
                        class="text-sm font-medium hover:underline"
                    >
                        View history
                    </Link>
                </div>
                <div class="divide-y">
                    <div
                        v-for="row in snapshot.health"
                        :key="row.name"
                        class="grid gap-2 px-4 py-3 sm:grid-cols-[130px_90px_1fr] sm:items-center"
                    >
                        <p class="text-sm font-medium">{{ row.name }}</p>
                        <span
                            class="inline-flex w-fit items-center gap-1 text-xs font-semibold"
                            :class="
                                row.status === 'healthy'
                                    ? 'text-emerald-700'
                                    : 'text-rose-700'
                            "
                        >
                            <CheckCircle2
                                v-if="row.status === 'healthy'"
                                class="size-3.5"
                            />
                            <AlertTriangle v-else class="size-3.5" />
                            {{ row.status }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs text-muted-foreground">
                                {{ details(row.details) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ dateTime(row.checkedAt) }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="snapshot.health.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No health checks recorded
                    </p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4 text-muted-foreground" />
                        <h2 class="font-semibold">Active alerts</h2>
                    </div>
                    <Link
                        href="/admin/operations/alerts"
                        class="text-sm font-medium hover:underline"
                    >
                        Manage
                    </Link>
                </div>
                <div class="divide-y">
                    <div
                        v-for="alert in snapshot.recentAlerts"
                        :key="alert.id"
                        class="px-4 py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium">{{ alert.title }}</p>
                            <span class="text-xs font-semibold text-amber-800">
                                {{ alert.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ alert.source }} ·
                            {{ dateTime(alert.lastDetectedAt) }}
                        </p>
                    </div>
                    <p
                        v-if="snapshot.recentAlerts.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No active alerts
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
