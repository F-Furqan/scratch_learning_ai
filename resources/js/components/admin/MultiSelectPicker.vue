<script setup lang="ts">
import { Check, ChevronDown, Search, X } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

type OptionValue = string | number;

type Option = {
    label: string;
    value: OptionValue;
};

const props = withDefaults(
    defineProps<{
        modelValue: OptionValue[];
        options: Option[];
        placeholder?: string;
    }>(),
    {
        placeholder: 'Select options',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: OptionValue[]];
}>();

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const search = ref('');

const selectedOptions = computed(() =>
    props.options.filter((option) => isSelected(option.value)),
);

const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.options;
    }

    return props.options.filter((option) =>
        option.label.toLowerCase().includes(query),
    );
});

const selectionLabel = computed(() => {
    if (selectedOptions.value.length === 0) {
        return props.placeholder;
    }

    const visible = selectedOptions.value
        .slice(0, 2)
        .map((option) => option.label)
        .join(', ');
    const remaining = selectedOptions.value.length - 2;

    return remaining > 0 ? `${visible} +${remaining}` : visible;
});

function isSelected(value: OptionValue) {
    return props.modelValue.some(
        (selected) => String(selected) === String(value),
    );
}

function toggle(value: OptionValue) {
    const next = isSelected(value)
        ? props.modelValue.filter(
              (selected) => String(selected) !== String(value),
          )
        : [...props.modelValue, value];

    emit('update:modelValue', next);
}

function clearSelection() {
    emit('update:modelValue', []);
}

onClickOutside(root, () => {
    open.value = false;
    search.value = '';
});
</script>

<template>
    <div ref="root" class="relative">
        <div
            class="flex min-h-10 overflow-hidden rounded-md border bg-background"
        >
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                aria-haspopup="listbox"
                :aria-expanded="open"
                @click="open = !open"
            >
                <span
                    class="truncate"
                    :class="
                        selectedOptions.length
                            ? 'font-medium text-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    {{ selectionLabel }}
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span
                        v-if="selectedOptions.length"
                        class="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary"
                    >
                        {{ selectedOptions.length }}
                    </span>
                    <ChevronDown
                        class="size-4 text-muted-foreground transition-transform"
                        :class="open ? 'rotate-180' : ''"
                    />
                </span>
            </button>
            <button
                v-if="selectedOptions.length"
                type="button"
                title="Clear selection"
                aria-label="Clear selection"
                class="grid w-10 shrink-0 place-items-center border-l text-muted-foreground hover:bg-muted hover:text-foreground"
                @click="clearSelection"
            >
                <X class="size-4" />
            </button>
        </div>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover p-2 text-popover-foreground shadow-lg"
        >
            <div class="relative mb-2">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus:border-primary"
                    placeholder="Search"
                    @keydown.esc="open = false"
                />
            </div>

            <div
                role="listbox"
                aria-multiselectable="true"
                class="max-h-56 overflow-y-auto"
            >
                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    role="option"
                    :aria-selected="isSelected(option.value)"
                    class="flex w-full items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                    @click="toggle(option.value)"
                >
                    <span
                        class="grid size-4 shrink-0 place-items-center rounded border"
                        :class="
                            isSelected(option.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        "
                    >
                        <Check v-if="isSelected(option.value)" class="size-3" />
                    </span>
                    <span class="truncate">{{ option.label }}</span>
                </button>
                <p
                    v-if="filteredOptions.length === 0"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    No matches
                </p>
            </div>
        </div>
    </div>
</template>
