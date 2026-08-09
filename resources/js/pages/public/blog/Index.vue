<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { BadgeCheck, Newspaper, Search, SlidersHorizontal } from '@lucide/vue';
import { reactive } from 'vue';

import BlogCard from '@/components/public/BlogCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type {
    BlogPostCard,
    Paginated,
    PublicTaxonomy,
    SeoPayload,
} from '@/types';

const props = defineProps<{
    posts: Paginated<BlogPostCard>;
    categories: PublicTaxonomy[];
    filters: {
        search: string;
        category: string;
    };
    seo: SeoPayload;
}>();

const filterState = reactive({ ...props.filters });

function submitFilters() {
    router.get(
        '/blog',
        Object.fromEntries(
            Object.entries(filterState).filter(([, value]) => value !== ''),
        ),
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="relative bg-[#101827] text-white">
        <div
            class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#b45309_50%,#0f766e)] opacity-85"
        />
        <img
            src="/template-assets/banner/banner-book.png"
            alt=""
            class="absolute right-[16%] bottom-14 hidden h-28 opacity-75 drop-shadow-[0_20px_45px_rgba(0,0,0,0.28)] lg:block"
        />
        <div
            class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.84),rgba(16,24,39,0.5))]"
        />
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p
                    class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-amber-100"
                >
                    <Newspaper class="h-4 w-4 text-amber-300" />
                    Editorial blog
                </p>
                <h1
                    class="mt-5 text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    Articles from approved learning experts.
                </h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-200">
                    Operational guidance, platform thinking, and practical
                    lessons from the editorial workflow.
                </p>
            </div>

            <div class="mt-10 grid max-w-2xl gap-3 sm:grid-cols-2">
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <div class="text-3xl font-black">{{ posts.total }}</div>
                    <div class="text-sm text-slate-300">Published articles</div>
                </div>
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <div class="text-3xl font-black">
                        {{ categories.length }}
                    </div>
                    <div class="text-sm text-slate-300">Topic categories</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f7f8fb] py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form
                class="grid gap-3 rounded-md border border-slate-200 bg-white p-4 shadow-[0_18px_50px_rgba(15,23,42,0.08)] md:grid-cols-[1fr_240px_auto]"
                @submit.prevent="submitFilters"
            >
                <label class="relative block">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="filterState.search"
                        type="search"
                        class="h-12 w-full rounded-md border border-slate-300 bg-slate-50 pr-3 pl-10 text-sm font-semibold transition outline-none focus:border-amber-600 focus:bg-white"
                        placeholder="Search articles"
                    />
                </label>
                <select
                    v-model="filterState.category"
                    class="h-12 rounded-md border border-slate-300 bg-slate-50 px-3 text-sm font-semibold transition outline-none focus:border-amber-600 focus:bg-white"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.slug"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <button
                    type="submit"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-amber-700"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    Filter
                </button>
            </form>

            <div
                class="mt-8 flex flex-wrap items-center justify-between gap-4 text-sm font-semibold text-slate-600"
            >
                <p class="inline-flex items-center gap-2">
                    <BadgeCheck class="h-4 w-4 text-teal-700" />
                    {{ posts.total }} published articles
                </p>
                <p v-if="posts.from && posts.to">
                    Showing {{ posts.from }}-{{ posts.to }}
                </p>
            </div>

            <div
                v-if="posts.data.length"
                class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <BlogCard
                    v-for="post in posts.data"
                    :key="post.id"
                    :post="post"
                />
            </div>
            <div
                v-else
                class="mt-6 rounded-md border border-dashed border-slate-300 bg-white p-8 text-slate-600"
            >
                No published articles match these filters.
            </div>

            <div class="mt-8">
                <PublicPagination :links="posts.links" />
            </div>
        </div>
    </section>
</template>
