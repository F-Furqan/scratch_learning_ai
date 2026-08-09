<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Newspaper,
    PenLine,
    UserRound,
} from '@lucide/vue';

import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type { BloggerProfile, Paginated, SeoPayload } from '@/types';

defineProps<{
    bloggers: Paginated<BloggerProfile>;
    seo: SeoPayload;
}>();

const fallbackAvatars = [
    '/template-assets/hero/hero-1.png',
    '/template-assets/hero/hero-8.png',
];

function authorAvatar(blogger: BloggerProfile) {
    if (!blogger.profile_photo_path) {
        return fallbackAvatars[blogger.id % fallbackAvatars.length];
    }

    return blogger.profile_photo_path.startsWith('http') ||
        blogger.profile_photo_path.startsWith('/')
        ? blogger.profile_photo_path
        : `/storage/${blogger.profile_photo_path}`;
}

function setFallbackAvatar(event: Event, src: string) {
    const image = event.target as HTMLImageElement;
    image.src = src;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="relative bg-[#101827] text-white">
        <div
            class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#b45309_50%,#0f766e)] opacity-85"
        />
        <div
            class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.84),rgba(16,24,39,0.5))]"
        />
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p
                    class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-amber-100"
                >
                    <PenLine class="h-4 w-4 text-amber-300" />
                    Approved authors
                </p>
                <h1
                    class="mt-5 text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    Voices behind the learning platform.
                </h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-200">
                    Authors shown here have passed the blogger approval workflow
                    and publish practical learning operations guidance.
                </p>
            </div>
        </div>
    </section>

    <section class="bg-[#f7f8fb] py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                v-if="bloggers.data.length"
                class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="blogger in bloggers.data"
                    :key="blogger.id"
                    :href="blogger.url"
                    class="group grid gap-5 rounded-md border border-slate-200 bg-white p-6 shadow-[0_16px_45px_rgba(15,23,42,0.08)] transition duration-300 hover:-translate-y-1 hover:border-amber-200 hover:shadow-[0_22px_60px_rgba(217,119,6,0.14)]"
                >
                    <div class="flex items-center gap-4">
                        <img
                            :src="authorAvatar(blogger)"
                            :alt="blogger.name"
                            class="h-16 w-16 rounded-md object-cover shadow-sm"
                            @error="
                                setFallbackAvatar(
                                    $event,
                                    fallbackAvatars[
                                        blogger.id % fallbackAvatars.length
                                    ],
                                )
                            "
                        />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2
                                    class="truncate text-xl font-black text-slate-950"
                                >
                                    {{ blogger.name }}
                                </h2>
                                <BadgeCheck
                                    v-if="blogger.is_verified_creator"
                                    class="h-5 w-5 shrink-0 text-amber-600"
                                />
                                <BadgeCheck
                                    v-if="blogger.is_verified_expert"
                                    class="h-5 w-5 shrink-0 text-teal-700"
                                />
                            </div>
                            <p class="text-sm font-semibold text-slate-500">
                                {{ blogger.expertise || 'Learning operations' }}
                            </p>
                        </div>
                    </div>

                    <p class="line-clamp-3 text-sm leading-6 text-slate-600">
                        {{ blogger.bio }}
                    </p>

                    <div
                        v-if="blogger.badges.length"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="badge in blogger.badges"
                            :key="badge.id"
                            class="rounded-md bg-amber-50 px-2 py-1 text-xs font-black text-amber-800"
                        >
                            {{ badge.name }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs font-black">
                        <span
                            class="inline-flex items-center justify-center gap-1.5 rounded-md bg-slate-50 px-2 py-2 text-slate-700"
                        >
                            <Newspaper class="h-3.5 w-3.5 text-amber-700" />
                            {{ blogger.post_count }} posts
                        </span>
                        <span
                            class="inline-flex items-center justify-center gap-1.5 rounded-md bg-slate-50 px-2 py-2 text-slate-700"
                        >
                            <UserRound class="h-3.5 w-3.5 text-teal-700" />
                            Author
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-slate-100 pt-4 text-sm font-black text-slate-950"
                    >
                        <span>View articles</span>
                        <ArrowRight
                            class="h-4 w-4 transition group-hover:translate-x-0.5"
                        />
                    </div>
                </Link>
            </div>
            <div
                v-else
                class="rounded-md border border-dashed border-slate-300 bg-white p-8 text-slate-600"
            >
                Approved blogger profiles will appear here.
            </div>

            <div class="mt-8">
                <PublicPagination :links="bloggers.links" />
            </div>
        </div>
    </section>
</template>
