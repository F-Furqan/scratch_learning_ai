<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LockKeyhole, MessageSquare, Users } from '@lucide/vue';

type ForumPayload = {
    id: number;
    title: string;
    description: string | null;
    visibility: string;
    threads_count: number;
    posts_count: number;
    url: string;
    course: { title: string; url: string } | null;
    category: { name: string } | null;
};

defineProps<{
    forums: ForumPayload[];
}>();
</script>

<template>
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <p
                class="text-sm font-semibold tracking-normal text-teal-700 uppercase"
            >
                Community
            </p>
            <h1
                class="mt-3 text-4xl font-semibold tracking-normal text-slate-950"
            >
                Course and category forums
            </h1>
            <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-600">
                Ask deeper questions, compare implementation notes, and keep
                discussions organized around the learning catalog.
            </p>
        </div>
    </section>

    <section class="bg-white py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div v-if="forums.length" class="grid gap-4 md:grid-cols-2">
                <Link
                    v-for="forum in forums"
                    :key="forum.id"
                    :href="forum.url"
                    class="rounded-md border border-slate-200 p-5 transition hover:border-slate-400"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">
                                {{ forum.title }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ forum.description }}
                            </p>
                        </div>
                        <LockKeyhole
                            v-if="forum.visibility !== 'public'"
                            class="h-5 w-5 text-amber-600"
                        />
                        <MessageSquare v-else class="h-5 w-5 text-teal-700" />
                    </div>
                    <div
                        class="mt-5 flex flex-wrap items-center gap-4 text-sm text-slate-500"
                    >
                        <span>{{ forum.threads_count }} threads</span>
                        <span>{{ forum.posts_count }} posts</span>
                        <span
                            v-if="forum.course"
                            class="inline-flex items-center gap-1"
                        >
                            <Users class="h-4 w-4" />
                            {{ forum.course.title }}
                        </span>
                        <span v-else-if="forum.category">
                            {{ forum.category.name }}
                        </span>
                    </div>
                </Link>
            </div>
            <div
                v-else
                class="rounded-md border border-dashed border-slate-300 p-8 text-slate-600"
            >
                No forums are available yet.
            </div>
        </div>
    </section>
</template>
