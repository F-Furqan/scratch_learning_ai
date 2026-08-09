<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-2">
        <component
            :is="link.url ? Link : 'span'"
            v-for="link in links"
            :key="link.label"
            :href="link.url || undefined"
            class="rounded-md border px-3 py-2 text-sm"
            :class="
                link.active
                    ? 'border-slate-950 bg-slate-950 text-white'
                    : link.url
                      ? 'border-slate-300 text-slate-700 hover:border-slate-950'
                      : 'border-slate-200 text-slate-400'
            "
        >
            <span v-html="link.label" />
        </component>
    </nav>
</template>
