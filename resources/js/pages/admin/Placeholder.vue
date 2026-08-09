<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">
                {{ title }}
            </h1>
            <p class="max-w-3xl text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <section class="rounded-lg border bg-card p-5">
            <h2 class="text-base font-semibold">Ready For Phase 2</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                The route, permission gate, navigation entry, and admin shell
                are in place. CRUD workflows can now be added inside this
                protected section.
            </p>
        </section>
    </div>
</template>
