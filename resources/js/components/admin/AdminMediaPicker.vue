<script setup lang="ts">
import { Image, X } from '@lucide/vue';
import { computed, ref } from 'vue';

type MediaAssetOption = {
    label: string;
    value: number | string;
    url: string | null;
};

const props = defineProps<{
    modelValue: number | string | null;
    assets: MediaAssetOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | string | null];
}>();

const open = ref(false);

const selected = computed(() =>
    props.assets.find(
        (asset) => String(asset.value) === String(props.modelValue ?? ''),
    ),
);

const selectAsset = (asset: MediaAssetOption) => {
    emit('update:modelValue', asset.value);
    open.value = false;
};
</script>

<template>
    <div class="space-y-2">
        <div
            class="flex min-h-14 items-center justify-between gap-3 rounded-md border px-3 py-2"
        >
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        v-if="selected?.url"
                        :src="selected.url"
                        :alt="selected.label"
                        class="size-full object-cover"
                    />
                    <Image v-else class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 text-sm">
                    <p class="truncate font-medium">
                        {{ selected?.label || 'No media selected' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            selected
                                ? `ID ${selected.value}`
                                : 'Choose from library'
                        }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted"
                    @click="open = !open"
                >
                    Choose
                </button>
                <button
                    v-if="selected"
                    type="button"
                    class="grid size-9 place-items-center rounded-md border hover:bg-muted"
                    @click="emit('update:modelValue', null)"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <div
            v-if="open"
            class="grid max-h-72 gap-2 overflow-y-auto rounded-md border bg-background p-2 sm:grid-cols-2 xl:grid-cols-3"
        >
            <button
                v-for="asset in assets"
                :key="asset.value"
                type="button"
                class="flex min-w-0 items-center gap-3 rounded-md border p-2 text-left hover:bg-muted"
                @click="selectAsset(asset)"
            >
                <div
                    class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        v-if="asset.url"
                        :src="asset.url"
                        :alt="asset.label"
                        class="size-full object-cover"
                    />
                    <Image v-else class="size-4 text-muted-foreground" />
                </div>
                <span class="truncate text-sm font-medium">{{
                    asset.label
                }}</span>
            </button>
        </div>
    </div>
</template>
