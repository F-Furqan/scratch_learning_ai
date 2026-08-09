<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeDollarSign,
    BookOpenCheck,
    LockKeyhole,
    Signal,
    Star,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import type { CourseCard } from '@/types';

const props = defineProps<{
    course: CourseCard;
}>();

const templateImages = [
    '/template-assets/course/course-21.jpg',
    '/template-assets/course/course-09.jpg',
    '/template-assets/course/course-01.jpg',
    '/template-assets/course/course-15.jpg',
    '/template-assets/course/course-03.jpg',
    '/template-assets/course/course-details-bg.jpg',
];

const fallbackImage = computed(
    () => templateImages[props.course.id % templateImages.length],
);
const courseImage = ref<string | null>(
    props.course.thumbnail?.url || fallbackImage.value,
);

function useFallbackImage() {
    courseImage.value = null;
}
</script>

<template>
    <Link :href="course.url" class="sl-template-card group grid">
        <div class="sl-card-image">
            <img
                v-if="courseImage"
                :src="courseImage"
                :alt="course.thumbnail?.alt_text || course.title"
                @error="useFallbackImage"
            />
            <div
                v-else
                class="grid h-full place-items-center bg-[linear-gradient(135deg,#0f172a,#0f766e_52%,#f59e0b)] px-6 text-center text-white"
            >
                <div>
                    <BookOpenCheck class="mx-auto h-12 w-12" />
                    <p class="mt-3 text-sm font-black uppercase">
                        Learning path
                    </p>
                </div>
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent"
            />
            <div class="sl-card-badge">
                {{ course.category?.name || 'Course' }}
            </div>
            <div class="sl-card-price inline-flex items-center gap-1.5">
                <BadgeDollarSign class="h-3.5 w-3.5" />
                {{ course.formatted_price }}
            </div>
        </div>

        <div class="sl-card-body">
            <div>
                <div
                    class="flex items-center gap-1 text-xs font-bold text-amber-500"
                    aria-label="Featured course"
                >
                    <Star class="h-3.5 w-3.5 fill-current" />
                    <Star class="h-3.5 w-3.5 fill-current" />
                    <Star class="h-3.5 w-3.5 fill-current" />
                    <Star class="h-3.5 w-3.5 fill-current" />
                    <Star class="h-3.5 w-3.5 fill-current" />
                </div>
                <h3 class="sl-card-title mt-3 line-clamp-2">
                    {{ course.title }}
                </h3>
                <p class="sl-card-text mt-2 line-clamp-3">
                    {{
                        course.short_description ||
                        'A practical learning path with guided lessons and editorial review.'
                    }}
                </p>
            </div>

            <div class="sl-card-meta">
                <span>
                    <BookOpenCheck class="h-3.5 w-3.5 text-teal-700" />
                    {{ course.lesson_count }} lessons
                </span>
                <span v-if="course.level" class="capitalize">
                    <Signal class="h-3.5 w-3.5 text-orange-600" />
                    {{ course.level }}
                </span>
                <span>
                    <LockKeyhole
                        v-if="!course.is_free"
                        class="h-3.5 w-3.5 text-slate-500"
                    />
                    {{ course.is_free ? 'Free' : 'Paid' }}
                </span>
            </div>

            <div
                class="flex items-center justify-between border-t border-slate-100 pt-4 text-sm font-black text-slate-950"
            >
                <span>View curriculum</span>
                <span
                    class="grid h-8 w-8 place-items-center rounded-md bg-slate-950 text-white transition group-hover:bg-teal-700"
                >
                    <ArrowRight class="h-4 w-4" />
                </span>
            </div>
        </div>
    </Link>
</template>
