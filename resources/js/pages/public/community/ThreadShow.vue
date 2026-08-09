<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Send, ShieldAlert, ThumbsUp } from '@lucide/vue';

type PostPayload = {
    id: number;
    body: string | null;
    author: string | null;
    upvotes_count: number;
    reaction_url: string;
    report_url: string;
    created_at: string | null;
};

type ThreadPayload = {
    id: number;
    title: string;
    body: string | null;
    author: string | null;
    forum: { title: string; url: string };
    is_locked: boolean;
    upvotes_count: number;
    post_store_url: string;
    reaction_url: string;
    report_url: string;
    created_at: string | null;
};

const props = defineProps<{
    thread: ThreadPayload;
    posts: PostPayload[];
}>();

const postForm = useForm({
    body: '',
});

function createPost() {
    postForm.post(props.thread.post_store_url, {
        preserveScroll: true,
        onSuccess: () => postForm.reset('body'),
    });
}

function postAction(url: string, payload: Record<string, string> = {}) {
    router.post(url, payload, {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <Link
                :href="thread.forum.url"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 hover:text-slate-950"
            >
                <ArrowLeft class="h-4 w-4" />
                {{ thread.forum.title }}
            </Link>
            <h1
                class="mt-5 text-4xl font-semibold tracking-normal text-slate-950"
            >
                {{ thread.title }}
            </h1>
            <p class="mt-2 text-sm font-medium text-slate-500">
                {{ thread.author || 'Member' }}
            </p>
            <p class="mt-5 text-base leading-7 text-slate-700">
                {{ thread.body }}
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-700"
                    @click="postAction(thread.reaction_url, { type: 'upvote' })"
                >
                    <ThumbsUp class="h-4 w-4" />
                    {{ thread.upvotes_count }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-semibold text-slate-700"
                    @click="
                        postAction(thread.report_url, {
                            reason: 'needs_review',
                        })
                    "
                >
                    <ShieldAlert class="h-4 w-4" />
                    Report
                </button>
            </div>
        </div>
    </section>

    <section class="bg-white py-10">
        <div class="mx-auto grid max-w-4xl gap-8 px-4 sm:px-6 lg:px-8">
            <form
                v-if="!thread.is_locked"
                class="grid gap-3 rounded-md border border-slate-200 p-5"
                @submit.prevent="createPost"
            >
                <h2 class="text-base font-semibold text-slate-950">Reply</h2>
                <textarea
                    v-model="postForm.body"
                    class="min-h-28 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-950"
                    placeholder="Add your reply"
                />
                <button
                    type="submit"
                    class="inline-flex h-10 w-fit items-center justify-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white"
                    :disabled="postForm.processing"
                >
                    <Send class="h-4 w-4" />
                    Reply
                </button>
            </form>

            <div v-if="posts.length" class="space-y-4">
                <article
                    v-for="post in posts"
                    :key="post.id"
                    class="rounded-md border border-slate-200 p-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-xs font-medium text-slate-500">
                            {{ post.author || 'Member' }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 px-2 text-xs font-semibold text-slate-700"
                                @click="
                                    postAction(post.reaction_url, {
                                        type: 'upvote',
                                    })
                                "
                            >
                                <ThumbsUp class="h-3.5 w-3.5" />
                                {{ post.upvotes_count }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 px-2 text-xs font-semibold text-slate-700"
                                @click="
                                    postAction(post.report_url, {
                                        reason: 'needs_review',
                                    })
                                "
                            >
                                <ShieldAlert class="h-3.5 w-3.5" />
                                Report
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-700">
                        {{ post.body }}
                    </p>
                </article>
            </div>
            <div
                v-else
                class="rounded-md border border-dashed border-slate-300 p-8 text-slate-600"
            >
                No approved replies yet.
            </div>
        </div>
    </section>
</template>
