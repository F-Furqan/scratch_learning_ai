<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    ExternalLink,
    Newspaper,
    PenLine,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import BlogCard from '@/components/public/BlogCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type {
    BloggerProfile,
    BlogPostCard,
    Paginated,
    SeoPayload,
} from '@/types';

const props = defineProps<{
    blogger: BloggerProfile;
    posts: Paginated<BlogPostCard>;
    seo: SeoPayload;
}>();

const fallbackAvatars = [
    '/template-assets/hero/hero-1.png',
    '/template-assets/hero/hero-8.png',
];

function storedPhotoUrl(path: string | null) {
    if (!path) {
        return null;
    }

    return path.startsWith('http') || path.startsWith('/')
        ? path
        : `/storage/${path}`;
}

const fallbackAvatar = computed(
    () => fallbackAvatars[props.blogger.id % fallbackAvatars.length],
);
const avatarUrl = ref(
    storedPhotoUrl(props.blogger.profile_photo_path) || fallbackAvatar.value,
);

function useFallbackAvatar() {
    avatarUrl.value = fallbackAvatar.value;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="relative bg-[#101827] text-white">
        <div
            class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#b45309_50%,#0f766e)] opacity-85"
        />
        <div
            class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.86),rgba(16,24,39,0.54))]"
        />
        <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
            <Link
                href="/bloggers"
                class="inline-flex items-center gap-2 text-sm font-bold text-slate-300 hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" />
                Authors
            </Link>

            <div class="mt-8 grid gap-7 md:grid-cols-[144px_1fr]">
                <img
                    :src="avatarUrl"
                    :alt="blogger.name"
                    class="h-36 w-36 rounded-md object-cover shadow-[0_22px_60px_rgba(0,0,0,0.3)]"
                    @error="useFallbackAvatar"
                />
                <div>
                    <p
                        class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-amber-100"
                    >
                        <PenLine class="h-4 w-4 text-amber-300" />
                        {{ blogger.expertise || 'Approved author' }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <h1
                            class="text-5xl leading-tight font-black tracking-normal"
                        >
                            {{ blogger.name }}
                        </h1>
                        <span
                            v-if="blogger.is_verified_creator"
                            class="inline-flex items-center gap-2 rounded-md bg-amber-300 px-3 py-2 text-sm font-black text-slate-950"
                        >
                            <BadgeCheck class="h-4 w-4" />
                            Verified creator
                        </span>
                        <span
                            v-if="blogger.is_verified_expert"
                            class="inline-flex items-center gap-2 rounded-md bg-teal-300 px-3 py-2 text-sm font-black text-slate-950"
                        >
                            <BadgeCheck class="h-4 w-4" />
                            Verified expert
                        </span>
                    </div>
                    <p class="mt-5 max-w-3xl leading-8 text-slate-200">
                        {{ blogger.bio }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            v-if="blogger.linkedin_url"
                            :href="blogger.linkedin_url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-white/20 px-3 text-sm font-black text-white transition hover:bg-white/10"
                        >
                            <ExternalLink class="h-4 w-4" />
                            LinkedIn
                        </a>
                        <a
                            v-if="blogger.website_url"
                            :href="blogger.website_url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-white/20 px-3 text-sm font-black text-white transition hover:bg-white/10"
                        >
                            <ExternalLink class="h-4 w-4" />
                            Website
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-10 grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <Newspaper class="h-5 w-5 text-amber-300" />
                    <div class="mt-3 text-3xl font-black">
                        {{ blogger.post_count }}
                    </div>
                    <div class="text-sm text-slate-300">Articles</div>
                </div>
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <BadgeCheck class="h-5 w-5 text-teal-300" />
                    <div class="mt-3 text-3xl font-black">
                        {{ blogger.badges.length }}
                    </div>
                    <div class="text-sm text-slate-300">Badges</div>
                </div>
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <UserRound class="h-5 w-5 text-indigo-300" />
                    <div class="mt-3 text-3xl font-black">1</div>
                    <div class="text-sm text-slate-300">Approved profile</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-black text-amber-700 uppercase">
                        Editorial
                    </p>
                    <h2
                        class="mt-2 text-3xl font-black tracking-normal text-slate-950"
                    >
                        Published articles
                    </h2>
                </div>
                <p class="text-sm font-bold text-slate-500">
                    {{ posts.total }} posts
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
                class="mt-6 rounded-md border border-dashed border-slate-300 bg-[#f7f8fb] p-8 text-slate-600"
            >
                This author does not have published articles yet.
            </div>
            <div class="mt-8">
                <PublicPagination :links="posts.links" />
            </div>
        </div>
    </section>
</template>
