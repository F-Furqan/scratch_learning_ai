<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    GraduationCap,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import { reactive } from 'vue';

import CourseCard from '@/components/public/CourseCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type {
    CourseCard as CourseCardType,
    Paginated,
    PublicTaxonomy,
    SeoPayload,
} from '@/types';

const props = defineProps<{
    courses: Paginated<CourseCardType>;
    categories: PublicTaxonomy[];
    filters: {
        search: string;
        category: string;
        level: string;
    };
    levels: string[];
    seo: SeoPayload;
}>();

const filterState = reactive({ ...props.filters });

function submitFilters() {
    router.get(
        '/courses',
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

    <section class="sl-breadcrumb">
        <div class="sl-container">
            <div class="max-w-3xl">
                <p
                    class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-teal-100"
                >
                    <BookOpenCheck class="h-4 w-4 text-amber-300" />
                    Course catalog
                </p>
                <h1
                    class="mt-5 text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    Courses built for applied teams.
                </h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-200">
                    Filter published learning paths by discipline, level, and
                    business need.
                </p>
            </div>

            <div class="mt-10 grid max-w-3xl gap-3 sm:grid-cols-3">
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <div class="text-3xl font-black">{{ courses.total }}</div>
                    <div class="text-sm text-slate-300">Published courses</div>
                </div>
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <div class="text-3xl font-black">
                        {{ categories.length }}
                    </div>
                    <div class="text-sm text-slate-300">Categories</div>
                </div>
                <div
                    class="rounded-md border border-white/15 bg-white/10 p-4 backdrop-blur"
                >
                    <div class="text-3xl font-black">{{ levels.length }}</div>
                    <div class="text-sm text-slate-300">Skill levels</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f7f8fb] py-10">
        <div class="sl-container">
            <form class="sl-filter-bar" @submit.prevent="submitFilters">
                <label class="relative block">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="filterState.search"
                        type="search"
                        class="pl-10"
                        placeholder="Search courses"
                    />
                </label>
                <select v-model="filterState.category">
                    <option value="">All categories</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.slug"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <select v-model="filterState.level" class="capitalize">
                    <option value="">All levels</option>
                    <option v-for="level in levels" :key="level" :value="level">
                        {{ level }}
                    </option>
                </select>
                <button type="submit" class="sl-btn sl-btn-primary">
                    <SlidersHorizontal class="h-4 w-4" />
                    Filter
                </button>
            </form>

            <div
                class="mt-8 flex flex-wrap items-center justify-between gap-4 text-sm font-semibold text-slate-600"
            >
                <p class="inline-flex items-center gap-2">
                    <GraduationCap class="h-4 w-4 text-teal-700" />
                    {{ courses.total }} published courses
                </p>
                <p v-if="courses.from && courses.to">
                    Showing {{ courses.from }}-{{ courses.to }}
                </p>
            </div>

            <div v-if="courses.data.length" class="sl-grid-3 mt-6">
                <CourseCard
                    v-for="course in courses.data"
                    :key="course.id"
                    :course="course"
                />
            </div>
            <div
                v-else
                class="mt-6 rounded-md border border-dashed border-slate-300 bg-white p-8 text-slate-600"
            >
                No published courses match these filters.
            </div>

            <div class="mt-8">
                <PublicPagination :links="courses.links" />
            </div>
        </div>
    </section>
</template>
