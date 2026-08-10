<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    GripVertical,
    Link2,
    Pencil,
    Plus,
    Save,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type MenuItem = {
    id: number;
    menu_id: number;
    parent_id: number | null;
    page_id: number | null;
    title: string;
    type: 'url' | 'page' | 'route';
    url: string | null;
    route_name: string | null;
    sort_order: number;
    is_active: boolean;
    page_title: string | null;
};

type OrderedItem = MenuItem & { depth: number };

const props = defineProps<{
    menu: {
        id: number;
        name: string;
        location: string | null;
        is_active: boolean;
    };
    items: MenuItem[];
    pages: Array<{ label: string; value: number }>;
    urls: {
        index: string;
        store: string;
        update_base: string;
        delete_base: string;
        reorder: string;
    };
}>();

const localItems = ref<MenuItem[]>(props.items.map((item) => ({ ...item })));
const editingId = ref<number | null>(null);
const editorOpen = ref(false);
const draggedId = ref<number | null>(null);
const savingOrder = ref(false);
const orderMessage = ref('');

watch(
    () => props.items,
    (items) => {
        localItems.value = items.map((item) => ({ ...item }));
    },
);

const form = useForm({
    menu_id: props.menu.id,
    parent_id: null as number | null,
    page_id: null as number | null,
    title: '',
    type: 'url' as 'url' | 'page' | 'route',
    url: '',
    route_name: '',
    sort_order: 0,
    is_active: true,
});

const orderedItems = computed<OrderedItem[]>(() => {
    const result: OrderedItem[] = [];
    const append = (parentId: number | null, depth: number) => {
        localItems.value
            .filter((item) => item.parent_id === parentId)
            .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
            .forEach((item) => {
                result.push({ ...item, depth });
                append(item.id, depth + 1);
            });
    };
    append(null, 0);

    return result;
});

const parentOptions = computed(() =>
    orderedItems.value.filter(
        (item) =>
            item.id !== editingId.value &&
            !isDescendant(item.id, editingId.value),
    ),
);

function isDescendant(candidateId: number, itemId: number | null) {
    if (!itemId) {
        return false;
    }

    let cursor = localItems.value.find((item) => item.id === candidateId);

    while (cursor?.parent_id) {
        if (cursor.parent_id === itemId) {
            return true;
        }

        cursor = localItems.value.find((item) => item.id === cursor?.parent_id);
    }

    return false;
}

function openCreate() {
    editingId.value = null;
    form.reset();
    form.menu_id = props.menu.id;
    form.sort_order = localItems.value.filter((item) => !item.parent_id).length;
    form.is_active = true;
    editorOpen.value = true;
}

function openEdit(item: MenuItem) {
    editingId.value = item.id;
    form.menu_id = item.menu_id;
    form.parent_id = item.parent_id;
    form.page_id = item.page_id;
    form.title = item.title;
    form.type = item.type;
    form.url = item.url || '';
    form.route_name = item.route_name || '';
    form.sort_order = item.sort_order;
    form.is_active = item.is_active;
    form.clearErrors();
    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingId.value = null;
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeEditor };

    if (editingId.value) {
        form.patch(`${props.urls.update_base}/${editingId.value}`, options);

        return;
    }

    form.post(props.urls.store, options);
}

function remove(item: MenuItem) {
    if (!window.confirm(`Delete “${item.title}”?`)) {
        return;
    }

    router.delete(`${props.urls.delete_base}/${item.id}`, {
        preserveScroll: true,
    });
}

function dropBefore(target: MenuItem) {
    const source = localItems.value.find((item) => item.id === draggedId.value);

    if (
        !source ||
        source.id === target.id ||
        isDescendant(target.id, source.id)
    ) {
        draggedId.value = null;

        return;
    }

    source.parent_id = target.parent_id;
    const siblings = localItems.value
        .filter(
            (item) =>
                item.parent_id === target.parent_id && item.id !== source.id,
        )
        .sort((a, b) => a.sort_order - b.sort_order);
    const targetIndex = siblings.findIndex((item) => item.id === target.id);
    siblings.splice(Math.max(targetIndex, 0), 0, source);
    siblings.forEach((item, index) => (item.sort_order = index));
    draggedId.value = null;
    orderMessage.value = 'Hierarchy changed. Save when ready.';
}

function normalizeOrder() {
    const groups = new Map<number | null, MenuItem[]>();
    localItems.value.forEach((item) => {
        const group = groups.get(item.parent_id) || [];
        group.push(item);
        groups.set(item.parent_id, group);
    });
    groups.forEach((items) =>
        items
            .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
            .forEach((item, index) => (item.sort_order = index)),
    );
}

function csrfToken() {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') || ''
    );
}

async function saveHierarchy() {
    normalizeOrder();
    savingOrder.value = true;
    orderMessage.value = '';

    try {
        const response = await fetch(props.urls.reorder, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                items: localItems.value.map((item) => ({
                    id: item.id,
                    parent_id: item.parent_id,
                    sort_order: item.sort_order,
                })),
            }),
        });

        if (!response.ok) {
            throw new Error('Unable to save the menu hierarchy.');
        }

        orderMessage.value = 'Menu hierarchy saved.';
        router.reload({ only: ['items'] });
    } catch (error) {
        orderMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to save hierarchy.';
    } finally {
        savingOrder.value = false;
    }
}
</script>

<template>
    <Head :title="`${menu.name} Menu Builder`" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <Link
                    :href="urls.index"
                    class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                    title="Back to menus"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">
                        {{ menu.name }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ menu.location || 'Unassigned location' }} ·
                        {{ menu.is_active ? 'Active' : 'Inactive' }}
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    :disabled="savingOrder || items.length === 0"
                    @click="saveHierarchy"
                >
                    <Save class="size-4" /> Save hierarchy
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                    @click="openCreate"
                >
                    <Plus class="size-4" /> New item
                </button>
            </div>
        </header>

        <p
            v-if="orderMessage"
            class="rounded-md border bg-muted/30 px-3 py-2 text-sm"
        >
            {{ orderMessage }}
        </p>

        <div class="grid min-h-0 gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="font-semibold">Navigation structure</h2>
                    <p class="text-xs text-muted-foreground">
                        Drag an item before another item. Use Parent to create
                        nested levels.
                    </p>
                </div>
                <div class="divide-y">
                    <article
                        v-for="item in orderedItems"
                        :key="item.id"
                        draggable="true"
                        class="grid min-h-14 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-3 py-2 hover:bg-muted/30"
                        :style="{
                            paddingLeft: `${12 + Math.min(item.depth, 5) * 28}px`,
                        }"
                        @dragstart="draggedId = item.id"
                        @dragover.prevent
                        @drop="dropBefore(item)"
                    >
                        <GripVertical
                            class="size-4 cursor-grab text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-semibold">
                                    {{ item.title }}
                                </p>
                                <span
                                    class="rounded border px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="
                                        item.is_active
                                            ? 'text-emerald-700'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ item.type }} ·
                                {{
                                    item.page_title ||
                                    item.url ||
                                    item.route_name ||
                                    'No target'
                                }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-md border hover:bg-muted"
                                title="Edit item"
                                @click="openEdit(item)"
                            >
                                <Pencil class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-md border text-destructive hover:bg-muted"
                                title="Delete item"
                                @click="remove(item)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </article>
                    <p
                        v-if="orderedItems.length === 0"
                        class="px-4 py-12 text-center text-sm text-muted-foreground"
                    >
                        This menu has no items yet.
                    </p>
                </div>
            </section>

            <aside
                v-if="editorOpen"
                class="h-fit rounded-lg border bg-card p-4 xl:sticky xl:top-5"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold">
                        {{ editingId ? 'Edit item' : 'New item' }}
                    </h2>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-md border"
                        @click="closeEditor"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <form class="grid gap-4" @submit.prevent="submit">
                    <label class="grid gap-1 text-sm font-medium">
                        Title
                        <input
                            v-model="form.title"
                            required
                            class="h-10 rounded-md border bg-background px-3"
                        />
                        <span
                            v-if="form.errors.title"
                            class="text-xs text-destructive"
                            >{{ form.errors.title }}</span
                        >
                    </label>
                    <label class="grid gap-1 text-sm font-medium">
                        Parent
                        <select
                            v-model="form.parent_id"
                            class="h-10 rounded-md border bg-background px-2"
                        >
                            <option :value="null">Top level</option>
                            <option
                                v-for="item in parentOptions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ '  '.repeat(item.depth) }}{{ item.title }}
                            </option>
                        </select>
                        <span
                            v-if="form.errors.parent_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.parent_id }}</span
                        >
                    </label>
                    <label class="grid gap-1 text-sm font-medium">
                        Link type
                        <select
                            v-model="form.type"
                            class="h-10 rounded-md border bg-background px-2"
                        >
                            <option value="url">URL</option>
                            <option value="page">CMS page</option>
                            <option value="route">Named route</option>
                        </select>
                    </label>
                    <label
                        v-if="form.type === 'page'"
                        class="grid gap-1 text-sm font-medium"
                    >
                        CMS page
                        <select
                            v-model="form.page_id"
                            class="h-10 rounded-md border bg-background px-2"
                        >
                            <option :value="null">Select page</option>
                            <option
                                v-for="page in pages"
                                :key="page.value"
                                :value="page.value"
                            >
                                {{ page.label }}
                            </option>
                        </select>
                        <span
                            v-if="form.errors.page_id"
                            class="text-xs text-destructive"
                            >{{ form.errors.page_id }}</span
                        >
                    </label>
                    <label
                        v-if="form.type === 'url'"
                        class="grid gap-1 text-sm font-medium"
                    >
                        URL
                        <div class="relative">
                            <Link2
                                class="absolute top-3 left-3 size-4 text-muted-foreground"
                            />
                            <input
                                v-model="form.url"
                                class="h-10 w-full rounded-md border bg-background pr-3 pl-9"
                                placeholder="/courses"
                            />
                        </div>
                        <span
                            v-if="form.errors.url"
                            class="text-xs text-destructive"
                            >{{ form.errors.url }}</span
                        >
                    </label>
                    <label
                        v-if="form.type === 'route'"
                        class="grid gap-1 text-sm font-medium"
                    >
                        Route name
                        <input
                            v-model="form.route_name"
                            class="h-10 rounded-md border bg-background px-3 font-mono text-sm"
                        />
                        <span
                            v-if="form.errors.route_name"
                            class="text-xs text-destructive"
                            >{{ form.errors.route_name }}</span
                        >
                    </label>
                    <label
                        class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium"
                    >
                        <input v-model="form.is_active" type="checkbox" />
                        Active
                    </label>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <Check class="size-4" /> Save item
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
