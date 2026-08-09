<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pin, Send } from '@lucide/vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import type { Paginated } from '@/types';

type ThreadPayload = {
    id: number;
    title: string;
    body: string | null;
    url: string;
    author: string | null;
    is_pinned: boolean;
    is_locked: boolean;
    replies_count: number;
    upvotes_count: number;
    last_activity_at: string | null;
};

type ForumPayload = {
    id: number;
    title: string;
    description: string | null;
    visibility: string;
    threads_count: number;
    posts_count: number;
    url: string;
    thread_store_url: string;
    course: { title: string; url: string } | null;
    category: { name: string } | null;
};

const props = defineProps<{
    forum: ForumPayload;
    threads: Paginated<ThreadPayload>;
}>();

const threadForm = useForm({
    title: '',
    body: '',
});

function createThread() {
    threadForm.post(props.forum.thread_store_url, {
        preserveScroll: true,
        onSuccess: () => threadForm.reset('title', 'body'),
    });
}
</script>

<template>
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <Link
                href="/community/forums"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-slate-950"
            >
                <ArrowLeft class="h-4 w-4" />
                Forums
            </Link>
            <h1
                class="mt-5 text-4xl font-semibold tracking-normal text-slate-950"
            >
                {{ forum.title }}
            </h1>
            <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-600">
                {{ forum.description }}
            </p>
            <div class="mt-5 flex flex-wrap gap-3 text-sm text-slate-500">
                <span>{{ forum.threads_count }} threads</span>
                <span>{{ forum.posts_count }} posts</span>
                <Link
                    v-if="forum.course"
                    :href="forum.course.url"
                    class="font-medium text-teal-700"
                >
                    {{ forum.course.title }}
                </Link>
                <span v-else-if="forum.category">{{
                    forum.category.name
                }}</span>
            </div>
        </div>
    </section>

    <section class="bg-white py-10">
        <div class="mx-auto grid max-w-5xl gap-8 px-4 sm:px-6 lg:px-8">
            <form
                class="grid gap-3 rounded-md border border-slate-200 p-5"
                @submit.prevent="createThread"
            >
                <h2 class="text-base font-semibold text-slate-950">
                    Start a thread
                </h2>
                <input
                    v-model="threadForm.title"
                    class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-slate-950"
                    placeholder="Thread title"
                />
                <textarea
                    v-model="threadForm.body"
                    class="min-h-28 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-950"
                    placeholder="Share the discussion topic"
                />
                <button
                    type="submit"
                    class="inline-flex h-10 w-fit items-center justify-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white"
                    :disabled="threadForm.processing"
                >
                    <Send class="h-4 w-4" />
                    Post
                </button>
            </form>

            <div v-if="threads.data.length" class="divide-y divide-slate-200">
                <Link
                    v-for="thread in threads.data"
                    :key="thread.id"
                    :href="thread.url"
                    class="block py-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <Pin
                                    v-if="thread.is_pinned"
                                    class="h-4 w-4 text-amber-600"
                                />
                                <h2 class="font-semibold text-slate-950">
                                    {{ thread.title }}
                                </h2>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ thread.body }}
                            </p>
                            <p class="mt-2 text-xs font-medium text-slate-500">
                                {{ thread.author || 'Member' }}
                            </p>
                        </div>
                        <div class="text-right text-sm text-slate-500">
                            <p>{{ thread.replies_count }} replies</p>
                            <p>{{ thread.upvotes_count }} upvotes</p>
                        </div>
                    </div>
                </Link>
            </div>
            <div
                v-else
                class="rounded-md border border-dashed border-slate-300 p-8 text-slate-600"
            >
                No approved threads yet.
            </div>

            <PublicPagination :links="threads.links" />
        </div>
    </section>
</template>
