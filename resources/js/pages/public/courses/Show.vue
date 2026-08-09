<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BadgeDollarSign,
    BookOpenCheck,
    CheckCircle2,
    CirclePlay,
    GraduationCap,
    Layers3,
    LockKeyhole,
    ShieldCheck,
    Star,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import CourseCard from '@/components/public/CourseCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type {
    CourseCard as CourseCardType,
    CourseDetail,
    SeoPayload,
} from '@/types';

const props = defineProps<{
    course: CourseDetail;
    relatedCourses: CourseCardType[];
    seo: SeoPayload;
}>();

const courseImage = ref(
    props.course.thumbnail?.url || null,
);

const firstLesson = computed(() => props.course.sections[0]?.lessons[0] || null);
const lessonCount = computed(() =>
    props.course.sections.reduce(
        (total, section) => total + section.lessons.length,
        0,
    ),
);

function useCourseFallback() {
    courseImage.value = null;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="relative bg-[#101827] text-white">
        <img
            v-if="courseImage"
            :src="courseImage"
            :alt="course.thumbnail?.alt_text || course.title"
            class="absolute inset-0 h-full w-full object-cover opacity-35"
            @error="useCourseFallback"
        />
        <div
            v-else
            class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#0f766e_52%,#f59e0b)] opacity-85"
        />
        <div
            class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.86),rgba(16,24,39,0.48))]"
        />
        <div
            class="relative mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_380px] lg:px-8"
        >
            <div>
                <Link
                    href="/courses"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-300 hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Courses
                </Link>
                <p
                    class="mt-8 inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-teal-100"
                >
                    <BookOpenCheck class="h-4 w-4 text-amber-300" />
                    {{ course.category?.name || 'Course' }}
                </p>
                <h1
                    class="mt-5 max-w-4xl text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    {{ course.title }}
                </h1>
                <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-200">
                    {{ course.short_description }}
                </p>

                <div class="mt-7 flex flex-wrap gap-2">
                    <span
                        class="inline-flex items-center gap-2 rounded-md bg-amber-300 px-3 py-1.5 text-sm font-black text-slate-950"
                    >
                        <BadgeDollarSign class="h-4 w-4" />
                        {{ course.formatted_price }}
                    </span>
                    <span
                        class="inline-flex items-center gap-2 rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-bold text-slate-100"
                    >
                        <Layers3 class="h-4 w-4" />
                        {{ course.sections.length }} sections
                    </span>
                    <span
                        class="inline-flex items-center gap-2 rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-bold text-slate-100"
                    >
                        <CirclePlay class="h-4 w-4" />
                        {{ lessonCount }} lessons
                    </span>
                    <span
                        v-if="course.level"
                        class="rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-bold text-slate-100 capitalize"
                    >
                        {{ course.level }}
                    </span>
                </div>
            </div>

            <aside
                class="h-fit overflow-hidden rounded-md border border-white/15 bg-white text-slate-950 shadow-[0_24px_70px_rgba(0,0,0,0.28)]"
            >
                <div class="relative aspect-[16/10] overflow-hidden bg-slate-900">
                    <img
                        v-if="courseImage"
                        :src="courseImage"
                        :alt="course.thumbnail?.alt_text || course.title"
                        class="h-full w-full object-cover"
                        @error="useCourseFallback"
                    />
                    <div
                        v-else
                        class="grid h-full place-items-center bg-[linear-gradient(135deg,#0f172a,#0f766e_52%,#f59e0b)] text-white"
                    >
                        <BookOpenCheck class="h-14 w-14" />
                    </div>
                    <div
                        class="absolute inset-0 grid place-items-center bg-slate-950/20"
                    >
                        <span
                            class="grid h-14 w-14 place-items-center rounded-md bg-white/95 text-slate-950 shadow-lg"
                        >
                            <CirclePlay class="h-7 w-7" />
                        </span>
                    </div>
                </div>
                <div class="grid gap-4 p-5">
                    <Link
                        v-if="firstLesson"
                        :href="firstLesson.url"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-teal-800"
                    >
                        Start first lesson
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link
                        v-if="!course.is_free && course.payment_plan"
                        :href="course.payment_plan.checkout_url"
                        method="post"
                        as="button"
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-amber-300 px-5 text-sm font-black text-slate-950 transition hover:bg-amber-200"
                    >
                        Buy course
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <div
                        class="grid gap-3 rounded-md bg-slate-50 p-4 text-sm text-slate-700"
                    >
                        <span class="inline-flex items-center gap-2">
                            <GraduationCap class="h-4 w-4 text-teal-700" />
                            {{
                                course.creator
                                    ? `Prepared by ${course.creator.name}`
                                    : 'Prepared by the editorial team'
                            }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <ShieldCheck class="h-4 w-4 text-teal-700" />
                            Access-aware lesson locking
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="bg-white py-14">
        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_340px] lg:px-8"
        >
            <article class="min-w-0">
                <div
                    class="rounded-md border border-slate-200 bg-white p-6 shadow-[0_16px_45px_rgba(15,23,42,0.06)]"
                >
                    <p class="text-sm font-black text-teal-700 uppercase">
                        About this course
                    </p>
                    <div
                        class="mt-4 text-base leading-8 whitespace-pre-line text-slate-700"
                    >
                        {{
                            course.description ||
                            'This course is being prepared by the editorial team.'
                        }}
                    </div>
                </div>

                <div class="mt-10">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p
                                class="text-sm font-black text-amber-700 uppercase"
                            >
                                Curriculum
                            </p>
                            <h2
                                class="mt-2 text-3xl font-black tracking-normal text-slate-950"
                            >
                                Lessons and sections
                            </h2>
                        </div>
                        <div class="flex items-center gap-1 text-amber-500">
                            <Star class="h-4 w-4 fill-current" />
                            <Star class="h-4 w-4 fill-current" />
                            <Star class="h-4 w-4 fill-current" />
                            <Star class="h-4 w-4 fill-current" />
                            <Star class="h-4 w-4 fill-current" />
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5">
                        <section
                            v-for="section in course.sections"
                            :key="section.id"
                            class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 p-5"
                            >
                                <div>
                                    <h3
                                        class="text-lg font-black text-slate-950"
                                    >
                                        {{ section.title }}
                                    </h3>
                                    <p
                                        v-if="section.description"
                                        class="mt-1 text-sm leading-6 text-slate-600"
                                    >
                                        {{ section.description }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-md bg-white px-2.5 py-1 text-xs font-black text-slate-700 shadow-sm"
                                >
                                    {{ section.lessons.length }} lessons
                                </span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <Link
                                    v-for="lesson in section.lessons"
                                    :key="lesson.id"
                                    :href="lesson.url"
                                    class="flex items-center justify-between gap-4 p-4 text-sm transition hover:bg-teal-50/70"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-slate-950 text-white"
                                        >
                                            <CirclePlay class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate font-black text-slate-900"
                                            >
                                                {{ lesson.title }}
                                            </span>
                                            <span
                                                class="text-xs text-slate-500"
                                            >
                                                {{
                                                    lesson.preview ||
                                                    'Guided lesson'
                                                }}
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-2 rounded-md px-2 py-1 text-xs font-black"
                                        :class="
                                            lesson.is_locked
                                                ? 'bg-slate-100 text-slate-600'
                                                : 'bg-teal-100 text-teal-800'
                                        "
                                    >
                                        <LockKeyhole
                                            v-if="lesson.is_locked"
                                            class="h-3.5 w-3.5"
                                        />
                                        {{
                                            lesson.is_locked ? 'Locked' : 'Open'
                                        }}
                                    </span>
                                </Link>
                            </div>
                        </section>
                    </div>
                </div>
            </article>

            <aside class="h-fit lg:sticky lg:top-28">
                <div
                    class="rounded-md border border-slate-200 bg-[#f7f8fb] p-5"
                >
                    <h2 class="font-black text-slate-950">What is included</h2>
                    <div class="mt-4 grid gap-3 text-sm text-slate-700">
                        <span class="inline-flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-teal-700" />
                            Published lesson workflow
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-teal-700" />
                            SEO-ready course detail
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-teal-700" />
                            Access-aware lessons
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-teal-700" />
                            Certificate-ready learning path
                        </span>
                    </div>
                </div>

                <div v-if="course.faqs.length" class="mt-6">
                    <h2 class="font-black text-slate-950">FAQs</h2>
                    <div class="mt-3 grid gap-3">
                        <details
                            v-for="faq in course.faqs"
                            :key="faq.id"
                            class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <summary
                                class="cursor-pointer text-sm font-black text-slate-900"
                            >
                                {{ faq.question }}
                            </summary>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ faq.answer }}
                            </p>
                        </details>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section
        v-if="relatedCourses.length"
        class="border-t border-slate-200 bg-[#f7f8fb] py-14"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-black text-teal-700 uppercase">
                        Next up
                    </p>
                    <h2
                        class="mt-2 text-3xl font-black tracking-normal text-slate-950"
                    >
                        Related courses
                    </h2>
                </div>
                <Link
                    href="/courses"
                    class="inline-flex h-11 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-teal-800"
                >
                    Browse catalog
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </div>
            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <CourseCard
                    v-for="related in relatedCourses"
                    :key="related.id"
                    :course="related"
                />
            </div>
        </div>
    </section>
</template>
