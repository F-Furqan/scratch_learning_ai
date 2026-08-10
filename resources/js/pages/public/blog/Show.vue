<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Newspaper,
    Tag,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';

import BlogCard from '@/components/public/BlogCard.vue';
import RichContent from '@/components/public/RichContent.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type { BlogPostCard, BlogPostDetail, SeoPayload } from '@/types';

const props = defineProps<{
    post: BlogPostDetail;
    relatedPosts: BlogPostCard[];
    seo: SeoPayload;
}>();

const postImage = ref(props.post.featured_image?.url || null);

function usePostFallback() {
    postImage.value = null;
}

function dateLabel(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('en', {
              month: 'long',
              day: 'numeric',
              year: 'numeric',
          }).format(new Date(value))
        : null;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <article>
        <section class="relative bg-[#101827] text-white">
            <img
                v-if="postImage"
                :src="postImage"
                :alt="post.featured_image?.alt_text || post.title"
                class="absolute inset-0 h-full w-full object-cover opacity-35"
                @error="usePostFallback"
            />
            <div
                v-else
                class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#b45309_50%,#0f766e)] opacity-85"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.86),rgba(16,24,39,0.44))]"
            />
            <div class="relative mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
                <Link
                    href="/blog"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-300 hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Blog
                </Link>
                <p
                    class="mt-8 inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-amber-100"
                >
                    <Newspaper class="h-4 w-4 text-amber-300" />
                    {{ post.category?.name || 'Article' }}
                </p>
                <h1
                    class="mt-5 max-w-4xl text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    {{ post.title }}
                </h1>
                <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-200">
                    {{ post.excerpt }}
                </p>
                <div
                    class="mt-7 flex flex-wrap items-center gap-3 text-sm font-semibold text-slate-200"
                >
                    <span class="inline-flex items-center gap-2">
                        <UserRound class="h-4 w-4 text-teal-300" />
                        {{ post.author?.name || 'Editorial team' }}
                    </span>
                    <span
                        v-if="dateLabel(post.published_at)"
                        class="inline-flex items-center gap-2"
                    >
                        <CalendarDays class="h-4 w-4 text-amber-300" />
                        {{ dateLabel(post.published_at) }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <BadgeCheck class="h-4 w-4 text-teal-300" />
                        Approved
                    </span>
                </div>
            </div>
        </section>

        <section class="bg-white py-12">
            <div
                class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_300px] lg:px-8"
            >
                <RichContent
                    class="min-w-0 rounded-md border border-slate-200 bg-white p-6 text-base leading-8 text-slate-700 shadow-[0_16px_45px_rgba(15,23,42,0.06)]"
                    :html="
                        post.content ||
                        '<p>This article is being prepared by the editorial team.</p>'
                    "
                />

                <aside class="h-fit lg:sticky lg:top-28">
                    <div
                        class="rounded-md border border-slate-200 bg-[#f7f8fb] p-5"
                    >
                        <h2 class="font-black text-slate-950">
                            Article details
                        </h2>
                        <div class="mt-4 grid gap-3 text-sm text-slate-600">
                            <span
                                v-if="post.category"
                                class="inline-flex items-center gap-2"
                            >
                                <Tag class="h-4 w-4 text-amber-700" />
                                {{ post.category.name }}
                            </span>
                            <Link
                                v-if="post.author?.url"
                                :href="post.author.url"
                                class="inline-flex items-center gap-2 font-black text-slate-950"
                            >
                                <UserRound class="h-4 w-4 text-teal-700" />
                                {{ post.author.name }}
                            </Link>
                        </div>
                        <div
                            v-if="post.tags.length"
                            class="mt-5 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="tag in post.tags"
                                :key="tag.id"
                                class="rounded-md bg-white px-2 py-1 text-xs font-black text-slate-700 shadow-sm"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section
            v-if="post.faqs.length"
            class="border-t border-slate-200 bg-[#f7f8fb] py-12"
        >
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <p class="text-sm font-black text-teal-700 uppercase">FAQs</p>
                <h2
                    class="mt-2 text-3xl font-black tracking-normal text-slate-950"
                >
                    Related questions
                </h2>
                <div class="mt-5 grid gap-3">
                    <details
                        v-for="faq in post.faqs"
                        :key="faq.id"
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <summary
                            class="cursor-pointer text-sm font-black text-slate-950"
                        >
                            {{ faq.question }}
                        </summary>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </div>
        </section>
    </article>

    <section
        v-if="relatedPosts.length"
        class="border-t border-slate-200 bg-white py-14"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-black text-amber-700 uppercase">
                        Keep reading
                    </p>
                    <h2
                        class="mt-2 text-3xl font-black tracking-normal text-slate-950"
                    >
                        Related articles
                    </h2>
                </div>
                <Link
                    href="/blog"
                    class="inline-flex h-11 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-amber-700"
                >
                    Blog index
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </div>
            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <BlogCard
                    v-for="related in relatedPosts"
                    :key="related.id"
                    :post="related"
                />
            </div>
        </div>
    </section>
</template>
