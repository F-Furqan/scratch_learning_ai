<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BadgeCheck, CalendarDays, Newspaper } from '@lucide/vue';
import { computed, ref } from 'vue';

import type { BlogPostCard } from '@/types';

const props = defineProps<{
    post: BlogPostCard;
}>();

const templateImages = [
    '/template-assets/blog/blog-1.jpg',
    '/template-assets/blog/blog-2.jpg',
    '/template-assets/blog/blog-3.jpg',
    '/template-assets/blog/blog-detail-image.jpg',
];

const fallbackImage = computed(
    () => templateImages[props.post.id % templateImages.length],
);
const postImage = ref<string | null>(
    props.post.featured_image?.url || fallbackImage.value,
);

function useFallbackImage() {
    postImage.value = null;
}

function dateLabel(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('en', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          }).format(new Date(value))
        : null;
}
</script>

<template>
    <Link :href="post.url" class="sl-template-card group grid">
        <div class="sl-card-image">
            <img
                v-if="postImage"
                :src="postImage"
                :alt="post.featured_image?.alt_text || post.title"
                @error="useFallbackImage"
            />
            <div
                v-else
                class="grid h-full place-items-center bg-[linear-gradient(135deg,#111827,#b45309_52%,#0f766e)] px-6 text-center text-white"
            >
                <div>
                    <Newspaper class="mx-auto h-12 w-12" />
                    <p class="mt-3 text-sm font-black uppercase">
                        Editorial insight
                    </p>
                </div>
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/15 to-transparent"
            />
            <div class="sl-card-badge inline-flex items-center gap-1.5">
                <Newspaper class="h-3.5 w-3.5 text-amber-600" />
                {{ post.category?.name || 'Article' }}
            </div>
            <span
                v-if="post.is_featured"
                class="absolute right-3 bottom-3 rounded-md bg-teal-300 px-2.5 py-1 text-xs font-black text-slate-950"
            >
                Featured
            </span>
        </div>

        <div class="sl-card-body">
            <div
                class="flex flex-wrap items-center gap-3 text-xs font-bold text-slate-500"
            >
                <span
                    v-if="dateLabel(post.published_at)"
                    class="inline-flex items-center gap-1.5"
                >
                    <CalendarDays class="h-3.5 w-3.5" />
                    {{ dateLabel(post.published_at) }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-teal-700">
                    <BadgeCheck class="h-3.5 w-3.5" />
                    Editorial approved
                </span>
            </div>

            <div>
                <h3 class="sl-card-title line-clamp-2">
                    {{ post.title }}
                </h3>
                <p class="sl-card-text mt-2 line-clamp-3">
                    {{
                        post.excerpt ||
                        'Practical guidance from the platform editorial team.'
                    }}
                </p>
            </div>

            <div
                class="flex items-center justify-between border-t border-slate-100 pt-4 text-sm"
            >
                <span class="min-w-0 truncate font-semibold text-slate-600">
                    {{ post.author?.name || 'Editorial team' }}
                </span>
                <span
                    class="inline-flex items-center gap-2 font-black text-slate-950"
                >
                    Read
                    <ArrowRight
                        class="h-4 w-4 transition group-hover:translate-x-0.5"
                    />
                </span>
            </div>
        </div>
    </Link>
</template>
