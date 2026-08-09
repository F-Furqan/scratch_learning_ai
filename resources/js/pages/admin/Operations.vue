<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, nextTick, reactive, ref } from 'vue';
import AdminMediaPicker from '@/components/admin/AdminMediaPicker.vue';

type Option = {
    label: string;
    value: string | number;
    url?: string | null;
};

type MediaAssetOption = {
    label: string;
    value: string | number;
    url: string | null;
};

type Field = {
    key: string;
    label: string;
    type: string;
    options: Option[];
    required: boolean;
};

type Column = {
    key: string;
    label: string;
};

type HistoryRow = {
    decision: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    actor: string | null;
    created_at: string | null;
};

type WorkflowAction = {
    label: string;
    url: string;
    tone: 'success' | 'warning' | 'danger' | string;
    method?: 'post' | 'delete';
};

type ReviewItem = {
    label: string;
    value: string | null;
};

type AdminRow = {
    id: number | string;
    form: Record<string, unknown>;
    history?: HistoryRow[];
    workflow_actions?: WorkflowAction[];
    review_items?: ReviewItem[];
    preview_url?: string | null;
    [key: string]: unknown;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRows = {
    data: AdminRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type BulkAction = {
    value: string;
    label: string;
    options: Option[];
    needsNote?: boolean;
};

type FormValue = string | number | boolean | null | Array<string | number>;

const props = defineProps<{
    resource: string;
    title: string;
    basePath: string;
    columns: Column[];
    fields: Field[];
    filters: Field[];
    filterValues: Record<string, string>;
    rows: PaginatedRows;
    bulkActions: BulkAction[];
    mediaAssets: MediaAssetOption[];
    metrics: Record<string, number>;
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
}>();

const editingRow = ref<AdminRow | null>(null);
const formOpen = ref(false);
const formPanel = ref<HTMLElement | null>(null);
const selectedIds = ref<Array<number | string>>([]);

const filterState = reactive<Record<string, string>>({
    search: props.filterValues.search || '',
    status: props.filterValues.status || '',
    category: props.filterValues.category || '',
    role: props.filterValues.role || '',
    group: props.filterValues.group || '',
});

const form = useForm<Record<string, FormValue>>(buildDefaults());

const bulk = reactive({
    action: props.bulkActions[0]?.value || '',
    value: '',
    note: '',
});

const selectedBulkAction = computed(() =>
    props.bulkActions.find((action) => action.value === bulk.action),
);

const allVisibleSelected = computed(
    () =>
        props.rows.data.length > 0 &&
        props.rows.data.every((row) => selectedIds.value.includes(row.id)),
);

const metricEntries = computed(() => Object.entries(props.metrics));
const canCreate = computed(() => props.canCreate ?? true);
const canEdit = computed(() => props.canEdit ?? true);
const canDelete = computed(() => props.canDelete ?? true);

function buildDefaults() {
    return props.fields.reduce<Record<string, FormValue>>((defaults, field) => {
        defaults[field.key] =
            field.type === 'checkbox'
                ? false
                : field.type === 'multiselect'
                  ? []
                  : null;

        return defaults;
    }, {});
}

function normalizeValue(field: Field, value: unknown): FormValue {
    if (field.type === 'checkbox') {
        return Boolean(value);
    }

    if (field.type === 'multiselect') {
        return Array.isArray(value) ? (value as Array<string | number>) : [];
    }

    return (value as FormValue) ?? null;
}

function resetForm(values: Record<string, unknown> = {}) {
    props.fields.forEach((field) => {
        form[field.key] = normalizeValue(field, values[field.key]);
    });
    form.clearErrors();
}

function openCreate() {
    editingRow.value = null;
    resetForm();
    formOpen.value = true;
    scrollToForm();
}

function openEdit(row: AdminRow) {
    editingRow.value = row;
    resetForm(row.form);
    formOpen.value = true;
    scrollToForm();
}

function scrollToForm() {
    nextTick(() => {
        formPanel.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    });
}

function closeForm() {
    formOpen.value = false;
    editingRow.value = null;
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    };

    if (editingRow.value) {
        form.patch(`${props.basePath}/${editingRow.value.id}`, options);

        return;
    }

    form.post(props.basePath, options);
}

function deleteRow(row: AdminRow) {
    router.delete(`${props.basePath}/${row.id}`, {
        preserveScroll: true,
    });
}

function runWorkflowAction(action: WorkflowAction) {
    if (action.method === 'delete') {
        router.delete(action.url, {
            preserveScroll: true,
        });

        return;
    }

    router.post(
        action.url,
        {},
        {
            preserveScroll: true,
        },
    );
}

function submitFilters() {
    router.get(
        props.basePath,
        Object.fromEntries(
            Object.entries(filterState).filter(([, value]) => value !== ''),
        ),
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function resetFilters() {
    filterState.search = '';
    filterState.status = '';
    filterState.category = '';
    filterState.role = '';
    filterState.group = '';
    submitFilters();
}

function toggleAllVisible() {
    if (allVisibleSelected.value) {
        selectedIds.value = selectedIds.value.filter(
            (id) => !props.rows.data.some((row) => row.id === id),
        );

        return;
    }

    selectedIds.value = Array.from(
        new Set([
            ...selectedIds.value,
            ...props.rows.data.map((row) => row.id),
        ]),
    );
}

function runBulkAction() {
    if (!bulk.action || selectedIds.value.length === 0) {
        return;
    }

    router.post(
        `${props.basePath}/bulk`,
        {
            ids: selectedIds.value,
            action: bulk.action,
            value: bulk.value || null,
            note: bulk.note || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                bulk.note = '';
            },
        },
    );
}

function textValue(key: string) {
    const value = form[key];

    return typeof value === 'string' || typeof value === 'number' ? value : '';
}

function mediaValue(key: string) {
    const value = form[key];

    return typeof value === 'string' || typeof value === 'number'
        ? value
        : null;
}

function multiValue(key: string) {
    const value = form[key];

    return Array.isArray(value) ? value : [];
}

function checkboxValue(key: string) {
    return Boolean(form[key]);
}

function setFormValue(key: string, value: FormValue) {
    form[key] = value;
}

function inputValue(event: Event) {
    return (event.target as HTMLInputElement | HTMLTextAreaElement).value;
}

function checkboxEventValue(event: Event) {
    return (event.target as HTMLInputElement).checked;
}

function multiSelectValue(event: Event) {
    return Array.from((event.target as HTMLSelectElement).selectedOptions).map(
        (option) => option.value,
    );
}

function displayCell(row: AdminRow, key: string) {
    const value = row[key];

    if (value === true) {
        return 'Yes';
    }

    if (value === false) {
        return 'No';
    }

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function paginationLabel(label: string) {
    return label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('&amp;', '&');
}

function workflowActionClass(action: WorkflowAction) {
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
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    {{ title }}
                </h1>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span
                        v-for="[key, value] in metricEntries"
                        :key="key"
                        class="rounded-md border px-2 py-1 font-medium"
                    >
                        {{ key.replaceAll('_', ' ') }}: {{ value }}
                    </span>
                </div>
            </div>

            <button
                v-if="canCreate"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                @click="openCreate"
            >
                <Plus class="size-4" />
                New
            </button>
        </div>

        <section class="rounded-lg border bg-card p-3">
            <form
                class="grid gap-2 md:grid-cols-[1fr_repeat(4,minmax(130px,180px))_auto]"
                @submit.prevent="submitFilters"
            >
                <label
                    v-for="filter in filters"
                    :key="filter.key"
                    class="flex flex-col gap-1 text-xs font-medium"
                    :class="filter.key === 'search' ? 'md:col-span-1' : ''"
                >
                    <span>{{ filter.label }}</span>
                    <div v-if="filter.type === 'search'" class="relative">
                        <Search
                            class="pointer-events-none absolute top-2.5 left-2 size-4 text-muted-foreground"
                        />
                        <input
                            v-model="filterState[filter.key]"
                            type="search"
                            class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm"
                        />
                    </div>
                    <select
                        v-else
                        v-model="filterState[filter.key]"
                        class="h-9 rounded-md border bg-background px-2 text-sm"
                    >
                        <option value="">All</option>
                        <option
                            v-for="option in filter.options"
                            :key="option.value"
                            :value="String(option.value)"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                        @click="resetFilters"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </form>
        </section>

        <section
            v-if="bulkActions.length"
            class="grid gap-2 rounded-lg border bg-card p-3 lg:grid-cols-[auto_minmax(140px,220px)_minmax(140px,220px)_1fr_auto]"
        >
            <div class="flex items-center text-sm font-medium">
                {{ selectedIds.length }} selected
            </div>
            <select
                v-model="bulk.action"
                class="h-9 rounded-md border bg-background px-2 text-sm"
            >
                <option
                    v-for="action in bulkActions"
                    :key="action.value"
                    :value="action.value"
                >
                    {{ action.label }}
                </option>
            </select>
            <select
                v-if="selectedBulkAction?.options.length"
                v-model="bulk.value"
                class="h-9 rounded-md border bg-background px-2 text-sm"
            >
                <option value="">Select value</option>
                <option
                    v-for="option in selectedBulkAction.options"
                    :key="option.value"
                    :value="String(option.value)"
                >
                    {{ option.label }}
                </option>
            </select>
            <div v-else></div>
            <input
                v-model="bulk.note"
                class="h-9 rounded-md border bg-background px-3 text-sm"
                type="text"
                placeholder="Note"
            />
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50"
                :disabled="selectedIds.length === 0 || !bulk.action"
                @click="runBulkAction"
            >
                <Check class="size-4" />
                Run
            </button>
        </section>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-10 px-3 py-3">
                                <input
                                    type="checkbox"
                                    :checked="allVisibleSelected"
                                    @change="toggleAllVisible"
                                />
                            </th>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="px-3 py-3"
                            >
                                {{ column.label }}
                            </th>
                            <th class="w-28 px-3 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-3 py-3 align-top">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="row.id"
                                />
                            </td>
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="max-w-[260px] px-3 py-3 align-top"
                            >
                                <span class="line-clamp-2">
                                    {{ displayCell(row, column.key) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 align-top">
                                <div class="flex flex-wrap justify-end gap-1">
                                    <button
                                        v-for="action in row.workflow_actions ||
                                        []"
                                        :key="action.url"
                                        type="button"
                                        class="h-8 rounded-md border px-2 text-xs font-semibold"
                                        :class="workflowActionClass(action)"
                                        @click="runWorkflowAction(action)"
                                    >
                                        {{ action.label }}
                                    </button>
                                    <button
                                        v-if="canEdit"
                                        type="button"
                                        class="grid size-8 place-items-center rounded-md border hover:bg-muted"
                                        @click="openEdit(row)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="grid size-8 place-items-center rounded-md border text-destructive hover:bg-muted"
                                        @click="deleteRow(row)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td
                                :colspan="columns.length + 2"
                                class="px-3 py-10 text-center text-sm text-muted-foreground"
                            >
                                No records found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-2 border-t px-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground">
                    Showing {{ rows.from || 0 }}-{{ rows.to || 0 }} of
                    {{ rows.total }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in rows.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-md border px-3 py-1.5 text-sm"
                            :class="
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted'
                            "
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                        <span
                            v-else
                            class="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                        >
                            {{ paginationLabel(link.label) }}
                        </span>
                    </template>
                </div>
            </div>
        </section>

        <section
            v-if="formOpen"
            ref="formPanel"
            class="rounded-lg border bg-card p-4"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold">
                    {{ editingRow ? 'Edit' : 'Create' }} {{ title }}
                </h2>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                    @click="closeForm"
                >
                    <X class="size-4" />
                </button>
            </div>

            <form
                class="grid gap-4 lg:grid-cols-2"
                @submit.prevent="submitForm"
            >
                <div
                    v-if="editingRow?.review_items?.length"
                    class="grid gap-3 rounded-md border bg-muted/30 p-3 md:grid-cols-2 lg:col-span-2"
                >
                    <div
                        v-for="item in editingRow.review_items"
                        :key="item.label"
                        class="rounded-md border bg-background p-3"
                    >
                        <p
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            {{ item.label }}
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ item.value || 'Missing' }}
                        </p>
                    </div>
                </div>

                <label
                    v-for="field in fields"
                    :key="field.key"
                    class="flex flex-col gap-1 text-sm font-medium"
                    :class="
                        ['textarea', 'media', 'multiselect'].includes(
                            field.type,
                        )
                            ? 'lg:col-span-2'
                            : ''
                    "
                >
                    <span>{{ field.label }}</span>
                    <textarea
                        v-if="field.type === 'textarea'"
                        :value="textValue(field.key)"
                        class="min-h-28 rounded-md border bg-background px-3 py-2 text-sm"
                        @input="setFormValue(field.key, inputValue($event))"
                    />
                    <select
                        v-else-if="field.type === 'select'"
                        :value="textValue(field.key)"
                        class="h-10 rounded-md border bg-background px-2 text-sm"
                        :required="field.required"
                        @change="setFormValue(field.key, inputValue($event))"
                    >
                        <option value="">None</option>
                        <option
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-else-if="field.type === 'multiselect'"
                        :value="multiValue(field.key)"
                        multiple
                        class="min-h-32 rounded-md border bg-background px-2 py-2 text-sm"
                        @change="
                            setFormValue(field.key, multiSelectValue($event))
                        "
                    >
                        <option
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <AdminMediaPicker
                        v-else-if="field.type === 'media'"
                        :model-value="mediaValue(field.key)"
                        :assets="mediaAssets"
                        @update:model-value="setFormValue(field.key, $event)"
                    />
                    <label
                        v-else-if="field.type === 'checkbox'"
                        class="flex h-10 items-center gap-2 rounded-md border px-3"
                    >
                        <input
                            :checked="checkboxValue(field.key)"
                            type="checkbox"
                            @change="
                                setFormValue(
                                    field.key,
                                    checkboxEventValue($event),
                                )
                            "
                        />
                        <span>{{ field.label }}</span>
                    </label>
                    <input
                        v-else
                        :value="textValue(field.key)"
                        :type="field.type"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        :required="field.required"
                        @input="setFormValue(field.key, inputValue($event))"
                    />
                    <span
                        v-if="form.errors[field.key]"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors[field.key] }}
                    </span>
                </label>

                <div
                    v-if="editingRow?.history?.length"
                    class="space-y-2 rounded-md border bg-muted/30 p-3 lg:col-span-2"
                >
                    <h3 class="text-sm font-semibold">Recent Decisions</h3>
                    <div
                        v-for="history in editingRow.history"
                        :key="`${history.created_at}-${history.to_status}`"
                        class="grid gap-1 border-t pt-2 text-xs first:border-t-0 first:pt-0 md:grid-cols-[160px_1fr]"
                    >
                        <span class="font-medium">
                            {{ history.from_status || 'new' }} →
                            {{ history.to_status || history.decision }}
                        </span>
                        <span class="text-muted-foreground">
                            {{ history.note || 'No note' }}
                            <span v-if="history.actor">
                                · {{ history.actor }}</span
                            >
                        </span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 lg:col-span-2">
                    <button
                        type="button"
                        class="rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted"
                        @click="closeForm"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Save
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
