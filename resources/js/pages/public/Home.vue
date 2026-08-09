<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    BookOpenCheck,
    ChartNoAxesCombined,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CirclePlay,
    LibraryBig,
    Newspaper,
    Search,
    ShieldCheck,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

import BlogCard from '@/components/public/BlogCard.vue';
import CourseCard from '@/components/public/CourseCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type {
    BlogPostCard,
    CourseCard as CourseCardType,
    FaqItem,
    HomeHero,
    HomePageSection,
    HomePageSectionCard,
    HomePageTestimonial,
    SeoPayload,
} from '@/types';

const props = defineProps<{
    site: {
        name: string;
        url: string;
        description: string;
    };
    hero: HomeHero;
    sections: HomePageSection[];
    courses: CourseCardType[];
    posts: BlogPostCard[];
    faqs: FaqItem[];
    seo: SeoPayload;
}>();

const activeSlideIndex = ref(0);
let sliderInterval: number | null = null;
const leadForms = reactive<
    Record<
        string,
        {
            name: string;
            email: string;
            message: string;
            processing: boolean;
        }
    >
>({});

const featuredCourse = computed(() => props.courses[0] || null);
const activeSlide = computed(
    () => props.hero.slides[activeSlideIndex.value] || null,
);
const showSlider = computed(
    () => props.hero.mode === 'slider' && props.hero.slides.length > 0,
);
const designDescription = computed(
    () => props.hero.design.description || props.site.description,
);
const designHeadingParts = computed(() =>
    highlightHeading(
        props.hero.design.heading,
        props.hero.design.highlight_terms,
    ),
);

const categoryHighlights = computed(() => [
    {
        label: 'Course catalog',
        value: props.courses.length,
        icon: LibraryBig,
        tone: 'bg-teal-50 text-teal-800 border-teal-100',
    },
    {
        label: 'Editorial articles',
        value: props.posts.length,
        icon: Newspaper,
        tone: 'bg-amber-50 text-amber-800 border-amber-100',
    },
    {
        label: 'Verified workflows',
        value: props.faqs.length,
        icon: ShieldCheck,
        tone: 'bg-indigo-50 text-indigo-800 border-indigo-100',
    },
]);

const iconComponents = {
    'badge-check': BadgeCheck,
    'book-open': BookOpenCheck,
    chart: ChartNoAxesCombined,
    'circle-play': CirclePlay,
    library: LibraryBig,
    newspaper: Newspaper,
    shield: ShieldCheck,
    sparkles: Sparkles,
    users: Users,
};

function sectionShellClass(section: HomePageSection) {
    const classes = {
        white: 'bg-white text-slate-950',
        soft: 'bg-[#f7f8fb] text-slate-950',
        dark: 'bg-[#101827] text-white',
        accent: 'bg-[#fff7ed] text-slate-950',
    };

    return (
        classes[section.background as keyof typeof classes] || classes.white
    );
}

function sectionEyebrowClass(section: HomePageSection) {
    return section.background === 'dark'
        ? 'text-amber-300'
        : 'text-teal-700';
}

function sectionMutedClass(section: HomePageSection) {
    return section.background === 'dark' ? 'text-slate-300' : 'text-slate-600';
}

function sectionCardClass(section: HomePageSection) {
    return section.background === 'dark'
        ? 'border-white/10 bg-white/[0.06] text-white'
        : 'border-slate-200 bg-white text-slate-950 shadow-[0_14px_40px_rgba(15,23,42,0.06)]';
}

function iconFor(card: HomePageSectionCard) {
    const icon = card.icon || 'sparkles';

    return iconComponents[icon as keyof typeof iconComponents] || Sparkles;
}

function ratingStars(item: HomePageTestimonial) {
    const rating = Number(item.rating ?? 5);

    return Math.min(Math.max(Number.isFinite(rating) ? rating : 5, 1), 5);
}

function leadForm(section: HomePageSection) {
    if (!leadForms[section.key]) {
        leadForms[section.key] = {
            name: '',
            email: '',
            message: '',
            processing: false,
        };
    }

    return leadForms[section.key];
}

function submitLead(section: HomePageSection) {
    const leadMagnet = section.data.lead_magnet;
    const form = leadForm(section);

    if (!leadMagnet || form.processing) {
        return;
    }

    form.processing = true;
    form.message = '';

    router.post(
        leadMagnet.action_url,
        {
            name: form.name || null,
            email: form.email,
            source_url: window.location.href,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                form.name = '';
                form.email = '';
                form.message = 'You are on the list.';
            },
            onError: () => {
                form.message = 'Check your email and try again.';
            },
            onFinish: () => {
                form.processing = false;
            },
        },
    );
}

function escapeRegex(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightHeading(heading: string, terms: string[]) {
    const cleanedTerms = terms.map((term) => term.trim()).filter(Boolean);

    if (!cleanedTerms.length) {
        return [{ text: heading, highlighted: false }];
    }

    const pattern = new RegExp(
        `(${cleanedTerms.map((term) => escapeRegex(term)).join('|')})`,
        'gi',
    );
    const normalizedTerms = new Set(
        cleanedTerms.map((term) => term.toLowerCase()),
    );

    return heading
        .split(pattern)
        .filter(Boolean)
        .map((text) => ({
            text,
            highlighted: normalizedTerms.has(text.toLowerCase()),
        }));
}

function goToSlide(index: number) {
    activeSlideIndex.value = index;
}

function nextSlide() {
    activeSlideIndex.value =
        (activeSlideIndex.value + 1) % props.hero.slides.length;
}

function previousSlide() {
    activeSlideIndex.value =
        (activeSlideIndex.value - 1 + props.hero.slides.length) %
        props.hero.slides.length;
}

onMounted(() => {
    if (props.hero.slides.length <= 1) {
        return;
    }

    sliderInterval = window.setInterval(nextSlide, 7000);
});

onBeforeUnmount(() => {
    if (sliderInterval !== null) {
        window.clearInterval(sliderInterval);
    }
});
</script>

<template>
    <SeoHead :seo="seo" />

    <section v-if="showSlider && activeSlide" class="sl-home-slider">
        <div class="sl-slider-stage">
            <a
                class="sl-slider-image-link"
                :href="activeSlide.target_url"
                :target="activeSlide.opens_in_new_tab ? '_blank' : undefined"
                :rel="
                    activeSlide.opens_in_new_tab ? 'noreferrer' : undefined
                "
                aria-label="Open hero slide"
            >
                <img
                    v-if="activeSlide.image_url"
                    :src="activeSlide.image_url"
                    :alt="activeSlide.image_alt || activeSlide.title"
                />
            </a>
            <div class="sl-slider-overlay" />
            <div class="sl-container sl-slider-content">
                <div
                    class="sl-slider-copy"
                    :class="`is-${activeSlide.text_position}`"
                >
                    <span v-if="activeSlide.eyebrow" class="sl-hero-kicker">
                        <Sparkles class="h-4 w-4" />
                        {{ activeSlide.eyebrow }}
                    </span>
                    <h1 class="sl-slider-title">
                        {{ activeSlide.title }}
                    </h1>
                    <p v-if="activeSlide.subtitle" class="sl-slider-copy-text">
                        {{ activeSlide.subtitle }}
                    </p>
                    <a
                        class="sl-btn sl-btn-secondary sl-slider-cta"
                        :href="activeSlide.target_url"
                        :target="
                            activeSlide.opens_in_new_tab ? '_blank' : undefined
                        "
                        :rel="
                            activeSlide.opens_in_new_tab
                                ? 'noreferrer'
                                : undefined
                        "
                    >
                        {{ activeSlide.button_label || 'Explore now' }}
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div v-if="hero.slides.length > 1" class="sl-slider-controls">
                <button
                    type="button"
                    class="sl-slider-arrow"
                    aria-label="Previous slide"
                    @click="previousSlide"
                >
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <div class="sl-slider-dots" aria-label="Hero slides">
                    <button
                        v-for="(slide, index) in hero.slides"
                        :key="slide.id"
                        type="button"
                        :class="{ 'is-active': index === activeSlideIndex }"
                        :aria-label="`Go to slide ${index + 1}`"
                        @click="goToSlide(index)"
                    />
                </div>
                <button
                    type="button"
                    class="sl-slider-arrow"
                    aria-label="Next slide"
                    @click="nextSlide"
                >
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>
        </div>
    </section>

    <section v-else class="sl-home-hero">
        <div class="sl-container sl-home-hero-grid">
            <div>
                <span class="sl-hero-kicker">
                    <Sparkles class="h-4 w-4" />
                    {{ hero.design.eyebrow }}
                </span>
                <h1 class="sl-hero-title">
                    <template
                        v-for="(part, index) in designHeadingParts"
                        :key="`${part.text}-${index}`"
                    >
                        <span v-if="part.highlighted">{{ part.text }}</span>
                        <template v-else>{{ part.text }}</template>
                    </template>
                </h1>
                <p class="sl-hero-copy">
                    {{ designDescription }}
                </p>

                <form
                    v-if="hero.design.search_enabled"
                    class="sl-hero-search"
                    action="/courses"
                    method="get"
                >
                    <select name="category" aria-label="Select category">
                        <option value="">Select Category</option>
                        <option value="software-engineering">
                            Software Engineering
                        </option>
                        <option value="learning-operations">
                            Learning Operations
                        </option>
                        <option value="business-growth">
                            Business Growth
                        </option>
                    </select>
                    <input
                        type="search"
                        name="search"
                        placeholder="Search for Courses, Instructors"
                    />
                    <button class="sl-btn sl-btn-secondary" type="submit">
                        <Search class="h-4 w-4" />
                    </button>
                </form>

                <div
                    v-if="hero.design.stats_enabled"
                    class="sl-hero-stats"
                    aria-label="Platform metrics"
                >
                    <div
                        v-for="item in categoryHighlights"
                        :key="item.label"
                        class="sl-stat-card"
                    >
                        <span class="sl-stat-icon">
                            <component :is="item.icon" class="h-5 w-5" />
                        </span>
                        <span>
                            <span class="sl-stat-value">{{
                                item.value
                            }}</span>
                            <span class="sl-stat-label">{{
                                item.label
                            }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="featuredCourse && hero.design.featured_course_enabled"
                class="sl-hero-feature"
            >
                <img
                    src="/template-assets/banner/banner-book.png"
                    alt=""
                    class="sl-hero-orbit"
                />
                <CourseCard :course="featuredCourse" />
            </div>
        </div>
    </section>

    <template v-for="section in sections" :key="section.key">
        <section
            v-if="section.type === 'course_categories'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p
                            v-if="section.eyebrow"
                            class="text-sm font-black uppercase"
                            :class="sectionEyebrowClass(section)"
                        >
                            {{ section.eyebrow }}
                        </p>
                        <h2
                            v-if="section.title"
                            class="mt-2 max-w-3xl text-4xl leading-tight font-black tracking-normal"
                        >
                            {{ section.title }}
                        </h2>
                        <p
                            v-if="section.subtitle"
                            class="mt-4 max-w-2xl leading-7"
                            :class="sectionMutedClass(section)"
                        >
                            {{ section.subtitle }}
                        </p>
                    </div>
                    <a
                        v-if="section.cta_url && section.cta_label"
                        :href="section.cta_url"
                        class="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-black text-slate-950 transition hover:border-teal-300 hover:text-teal-800"
                    >
                        {{ section.cta_label }}
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>

                <div
                    v-if="section.data.categories?.length"
                    class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <a
                        v-for="category in section.data.categories"
                        :key="category.id"
                        :href="category.url"
                        class="group rounded-md border border-slate-200 bg-white p-5 shadow-[0_14px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:border-teal-300"
                    >
                        <span
                            class="grid h-12 w-12 place-items-center rounded-md bg-teal-50 text-teal-800"
                        >
                            <LibraryBig class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-lg font-black text-slate-950">
                            {{ category.name }}
                        </h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                            {{ category.description }}
                        </p>
                        <div
                            class="mt-4 inline-flex items-center gap-2 text-sm font-black text-teal-800"
                        >
                            {{ category.course_count }} courses
                            <ArrowRight
                                class="h-4 w-4 transition group-hover:translate-x-1"
                            />
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'featured_courses'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p
                            v-if="section.eyebrow"
                            class="text-sm font-black uppercase"
                            :class="sectionEyebrowClass(section)"
                        >
                            {{ section.eyebrow }}
                        </p>
                        <h2
                            v-if="section.title"
                            class="mt-2 max-w-3xl text-4xl leading-tight font-black tracking-normal"
                        >
                            {{ section.title }}
                        </h2>
                        <p
                            v-if="section.subtitle"
                            class="mt-4 max-w-2xl leading-7"
                            :class="sectionMutedClass(section)"
                        >
                            {{ section.subtitle }}
                        </p>
                    </div>
                    <a
                        v-if="section.cta_url && section.cta_label"
                        :href="section.cta_url"
                        class="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-black text-slate-950 transition hover:border-teal-300 hover:text-teal-800"
                    >
                        {{ section.cta_label }}
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>

                <div
                    v-if="section.data.courses?.length"
                    class="mt-9 grid gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <CourseCard
                        v-for="course in section.data.courses"
                        :key="course.id"
                        :course="course"
                    />
                </div>
                <div
                    v-else
                    class="mt-9 rounded-md border border-dashed border-slate-300 bg-slate-50 p-8 text-slate-600"
                >
                    Published courses will appear here once the catalog is approved.
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'learning_paths'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-[0.78fr_1.22fr]">
                    <div>
                        <p
                            v-if="section.eyebrow"
                            class="text-sm font-black uppercase"
                            :class="sectionEyebrowClass(section)"
                        >
                            {{ section.eyebrow }}
                        </p>
                        <h2
                            v-if="section.title"
                            class="mt-2 text-4xl leading-tight font-black tracking-normal"
                        >
                            {{ section.title }}
                        </h2>
                        <p
                            v-if="section.subtitle"
                            class="mt-4 leading-7"
                            :class="sectionMutedClass(section)"
                        >
                            {{ section.subtitle }}
                        </p>
                        <a
                            v-if="section.cta_url && section.cta_label"
                            :href="section.cta_url"
                            class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-teal-800"
                        >
                            {{ section.cta_label }}
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </div>
                    <div class="grid gap-4">
                        <a
                            v-for="path in section.data.paths"
                            :key="path.id"
                            :href="path.url"
                            class="group rounded-md border border-slate-200 bg-white p-5 shadow-[0_14px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:border-amber-300"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-4"
                            >
                                <div>
                                    <h3
                                        class="text-xl font-black text-slate-950"
                                    >
                                        {{ path.title }}
                                    </h3>
                                    <p
                                        class="mt-2 max-w-3xl text-sm leading-6 text-slate-600"
                                    >
                                        {{ path.description }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-amber-100 px-3 py-1 text-sm font-black text-amber-900"
                                >
                                    {{ path.course_count }} courses
                                </span>
                            </div>
                            <span
                                class="mt-4 inline-flex items-center gap-2 text-sm font-black text-teal-800"
                            >
                                View path
                                <ArrowRight
                                    class="h-4 w-4 transition group-hover:translate-x-1"
                                />
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'why_scratch_learning'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8"
            >
                <div>
                    <p
                        v-if="section.eyebrow"
                        class="text-sm font-black uppercase"
                        :class="sectionEyebrowClass(section)"
                    >
                        {{ section.eyebrow }}
                    </p>
                    <h2
                        v-if="section.title"
                        class="mt-2 text-4xl leading-tight font-black tracking-normal"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        v-if="section.subtitle"
                        class="mt-5 max-w-xl leading-7"
                        :class="sectionMutedClass(section)"
                    >
                        {{ section.subtitle }}
                    </p>
                    <a
                        v-if="section.cta_url && section.cta_label"
                        :href="section.cta_url"
                        class="mt-7 inline-flex h-11 items-center gap-2 rounded-md bg-white px-4 text-sm font-black text-slate-950 transition hover:bg-amber-100"
                    >
                        {{ section.cta_label }}
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="(card, cardIndex) in section.data.cards"
                        :key="`${card.title || 'card'}-${cardIndex}`"
                        class="rounded-md border p-5"
                        :class="sectionCardClass(section)"
                    >
                        <component
                            :is="iconFor(card)"
                            class="h-7 w-7 text-amber-300"
                        />
                        <h3 class="mt-4 font-black">{{ card.title }}</h3>
                        <p
                            class="mt-2 text-sm leading-6"
                            :class="sectionMutedClass(section)"
                        >
                            {{ card.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'testimonials'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p
                        v-if="section.eyebrow"
                        class="text-sm font-black uppercase"
                        :class="sectionEyebrowClass(section)"
                    >
                        {{ section.eyebrow }}
                    </p>
                    <h2
                        v-if="section.title"
                        class="mt-2 text-4xl leading-tight font-black tracking-normal"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        v-if="section.subtitle"
                        class="mt-4 leading-7"
                        :class="sectionMutedClass(section)"
                    >
                        {{ section.subtitle }}
                    </p>
                </div>
                <div class="mt-9 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="item in section.data.items"
                        :key="`${item.name}-${item.role}`"
                        class="rounded-md border border-slate-200 bg-white p-6 shadow-[0_14px_40px_rgba(15,23,42,0.06)]"
                    >
                        <div class="flex gap-1 text-amber-400">
                            <span
                                v-for="star in ratingStars(item)"
                                :key="star"
                                aria-hidden="true"
                            >
                                ★
                            </span>
                        </div>
                        <p class="mt-5 text-lg leading-8 text-slate-700">
                            “{{ item.quote }}”
                        </p>
                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <h3 class="font-black text-slate-950">
                                {{ item.name }}
                            </h3>
                            <p class="mt-1 text-sm font-bold text-teal-800">
                                {{ item.role }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'latest_blogs'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div
                class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.82fr_1.18fr] lg:px-8"
            >
                <div>
                    <p
                        v-if="section.eyebrow"
                        class="text-sm font-black uppercase"
                        :class="sectionEyebrowClass(section)"
                    >
                        {{ section.eyebrow }}
                    </p>
                    <h2
                        v-if="section.title"
                        class="mt-2 text-4xl leading-tight font-black tracking-normal"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        v-if="section.subtitle"
                        class="mt-4 max-w-xl leading-7"
                        :class="sectionMutedClass(section)"
                    >
                        {{ section.subtitle }}
                    </p>
                    <a
                        v-if="section.cta_url && section.cta_label"
                        :href="section.cta_url"
                        class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-teal-800"
                    >
                        {{ section.cta_label }}
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>
                <div
                    v-if="section.data.posts?.length"
                    class="grid gap-6 md:grid-cols-2"
                >
                    <BlogCard
                        v-for="post in section.data.posts"
                        :key="post.id"
                        :post="post"
                    />
                </div>
                <div
                    v-else
                    class="rounded-md border border-dashed border-slate-300 bg-white p-8 text-slate-600"
                >
                    Published blog posts will appear here after editorial approval.
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'faq' && section.data.faqs?.length"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.75fr_1.25fr] lg:px-8"
            >
                <div>
                    <p
                        v-if="section.eyebrow"
                        class="text-sm font-black uppercase"
                        :class="sectionEyebrowClass(section)"
                    >
                        {{ section.eyebrow }}
                    </p>
                    <h2
                        v-if="section.title"
                        class="mt-2 text-4xl leading-tight font-black tracking-normal"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        v-if="section.subtitle"
                        class="mt-4 leading-7"
                        :class="sectionMutedClass(section)"
                    >
                        {{ section.subtitle }}
                    </p>
                </div>
                <div class="grid gap-3">
                    <details
                        v-for="faq in section.data.faqs"
                        :key="faq.id"
                        class="group rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-black text-slate-950"
                        >
                            {{ faq.question }}
                            <CheckCircle2
                                class="h-5 w-5 shrink-0 text-teal-700"
                            />
                        </summary>
                        <p class="mt-3 leading-7 text-slate-600">
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <section
            v-else-if="section.type === 'newsletter_lead_magnet'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div
                class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8"
            >
                <div>
                    <p
                        v-if="section.eyebrow"
                        class="text-sm font-black uppercase"
                        :class="sectionEyebrowClass(section)"
                    >
                        {{ section.eyebrow }}
                    </p>
                    <h2
                        v-if="section.title"
                        class="mt-2 text-4xl leading-tight font-black tracking-normal"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        v-if="section.subtitle"
                        class="mt-4 leading-7"
                        :class="sectionMutedClass(section)"
                    >
                        {{ section.subtitle }}
                    </p>
                </div>

                <form
                    class="rounded-md border border-slate-200 bg-white p-6 shadow-[0_14px_40px_rgba(15,23,42,0.08)]"
                    @submit.prevent="submitLead(section)"
                >
                    <h3 class="text-2xl font-black text-slate-950">
                        {{
                            section.data.lead_magnet?.form_headline ||
                            section.data.lead_magnet?.title ||
                            section.cta_label ||
                            'Join the newsletter'
                        }}
                    </h3>
                    <p class="mt-2 leading-7 text-slate-600">
                        {{
                            section.data.lead_magnet?.description ||
                            section.body ||
                            'Get useful updates, launch offers, and practical learning resources.'
                        }}
                    </p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <input
                            :value="leadForm(section).name"
                            class="h-12 rounded-md border border-slate-200 px-4 text-sm font-bold text-slate-950 outline-none focus:border-teal-500"
                            type="text"
                            placeholder="Your name"
                            @input="
                                leadForm(section).name = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                        <input
                            :value="leadForm(section).email"
                            class="h-12 rounded-md border border-slate-200 px-4 text-sm font-bold text-slate-950 outline-none focus:border-teal-500"
                            type="email"
                            placeholder="Email address"
                            required
                            @input="
                                leadForm(section).email = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                    </div>
                    <button
                        class="mt-4 inline-flex h-12 items-center gap-2 rounded-md bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-teal-800 disabled:opacity-60"
                        type="submit"
                        :disabled="
                            leadForm(section).processing ||
                            !section.data.lead_magnet
                        "
                    >
                        {{
                            String(
                                section.payload.button_label ||
                                    section.cta_label ||
                                    'Subscribe',
                            )
                        }}
                        <ArrowRight class="h-4 w-4" />
                    </button>
                    <p
                        v-if="leadForm(section).message"
                        class="mt-3 text-sm font-bold text-teal-800"
                    >
                        {{ leadForm(section).message }}
                    </p>
                </form>
            </div>
        </section>

        <section
            v-else-if="section.type === 'final_cta'"
            class="py-16"
            :class="sectionShellClass(section)"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="relative overflow-hidden rounded-md bg-[#08111f] px-6 py-10 text-white shadow-[0_24px_70px_rgba(8,17,31,0.22)] sm:px-10"
                >
                    <img
                        src="/template-assets/banner/banner-book.png"
                        alt=""
                        class="absolute right-6 bottom-0 hidden h-40 opacity-80 md:block"
                    />
                    <div class="relative max-w-3xl">
                        <BookOpenCheck class="h-9 w-9 text-amber-300" />
                        <p
                            v-if="section.eyebrow"
                            class="mt-4 text-sm font-black text-amber-300 uppercase"
                        >
                            {{ section.eyebrow }}
                        </p>
                        <h2
                            v-if="section.title"
                            class="mt-2 text-3xl leading-tight font-black tracking-normal"
                        >
                            {{ section.title }}
                        </h2>
                        <p
                            v-if="section.subtitle"
                            class="mt-3 leading-7 text-slate-300"
                        >
                            {{ section.subtitle }}
                        </p>
                        <div
                            v-if="section.data.metrics?.length"
                            class="mt-6 grid gap-3 sm:grid-cols-3"
                        >
                            <div
                                v-for="metric in section.data.metrics"
                                :key="`${metric.metric}-${metric.label}`"
                                class="rounded-md border border-white/10 bg-white/[0.06] p-4"
                            >
                                <div class="text-2xl font-black text-white">
                                    {{ metric.metric }}
                                </div>
                                <div class="mt-1 text-sm font-bold text-slate-300">
                                    {{ metric.label }}
                                </div>
                            </div>
                        </div>
                        <a
                            v-if="section.cta_url && section.cta_label"
                            :href="section.cta_url"
                            class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-amber-300 px-4 text-sm font-black text-slate-950 transition hover:bg-amber-200"
                        >
                            {{ section.cta_label }}
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </template>
</template>
