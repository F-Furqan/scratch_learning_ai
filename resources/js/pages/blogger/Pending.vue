<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    status: string;
}>();
</script>

<template>
    <Head title="Creator Application" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">
                Creator Application
            </h1>
            <p class="text-sm text-muted-foreground">
                Your current creator status is {{ status }}.
            </p>
        </div>

        <section class="rounded-lg border bg-card p-5">
            <h2 class="text-base font-semibold">Waiting For Review</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Admin approval is required before creator publishing tools are
                enabled.
            </p>
        </section>
    </div>
</template>
