<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, FileCheck2, ShieldCheck } from '@lucide/vue';

type GuidelineSection = {
    title: string;
    items: string[];
};

defineProps<{
    sections: GuidelineSection[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator guidelines',
                href: '/creator/guidelines',
            },
        ],
    },
});
</script>

<template>
    <Head title="Creator Guidelines" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary">
                    Creator operations
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal">
                    Content guidelines
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    These rules protect students, creators, and Scratch
                    Learning. Creator actions create requests or revisions;
                    admin actions make publishing and deletion decisions.
                </p>
            </div>
            <Link
                href="/creator/dashboard"
                class="rounded-md border px-4 py-2 text-sm font-semibold"
            >
                Dashboard
            </Link>
        </div>

        <section class="grid gap-4 lg:grid-cols-3">
            <article
                v-for="section in sections"
                :key="section.title"
                class="rounded-lg border bg-card p-5"
            >
                <div class="flex items-center gap-2">
                    <ShieldCheck class="h-5 w-5 text-teal-700" />
                    <h2 class="text-base font-semibold">{{ section.title }}</h2>
                </div>
                <ul
                    class="mt-4 grid gap-3 text-sm leading-6 text-muted-foreground"
                >
                    <li
                        v-for="item in section.items"
                        :key="item"
                        class="flex gap-2"
                    >
                        <BadgeCheck
                            class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                        />
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </article>
        </section>

        <section
            class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-950"
        >
            <div class="flex gap-3">
                <FileCheck2 class="mt-0.5 h-5 w-5 text-amber-700" />
                <div>
                    <h2 class="font-semibold">Required declaration</h2>
                    <p class="mt-1 text-sm leading-6">
                        Before submitting a blog, course, or major revision, you
                        must confirm the work is original or properly licensed
                        and that you understand false declarations may lead to
                        removal or account action.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
