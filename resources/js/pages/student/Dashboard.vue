<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    BookOpenCheck,
    Bookmark,
    CheckCircle2,
    Clock3,
    FileText,
    PlayCircle,
} from '@lucide/vue';

type StatPayload = {
    active_courses: number;
    completed_courses: number;
    saved_lessons: number;
    certificates: number;
};

type EnrollmentPayload = {
    id: number;
    course_title: string | null;
    course_url: string | null;
    last_lesson_title: string | null;
    last_lesson_url: string | null;
    status: string | null;
    progress_percent: number;
};

type ContinuePayload = {
    id: number;
    course_title: string | null;
    lesson_title: string | null;
    lesson_url: string | null;
    status: string | null;
    progress_percent: number;
};

type BookmarkPayload = {
    id: number;
    label: string | null;
    course_title: string | null;
    lesson_title: string | null;
    lesson_url: string | null;
};

type NotePayload = {
    id: number;
    body: string;
    course_title: string | null;
    lesson_title: string | null;
    lesson_url: string | null;
};

type CertificatePayload = {
    id: number;
    certificate_number: string;
    course_title: string | null;
    verification_url: string;
    status: string | null;
};

defineProps<{
    stats: StatPayload;
    enrollments: EnrollmentPayload[];
    continue_watching: ContinuePayload[];
    bookmarks: BookmarkPayload[];
    notes: NotePayload[];
    certificates: CertificatePayload[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Learning',
                href: '/student/dashboard',
            },
        ],
    },
});
</script>

<template>
    <Head title="My Learning" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    My Learning
                </h1>
                <p class="text-sm text-muted-foreground">
                    Your courses, saves, notes, and certificates in one place.
                </p>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <BookOpenCheck class="h-5 w-5 text-teal-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ stats.active_courses }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Active courses
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ stats.completed_courses }}
                        </p>
                        <p class="text-xs text-muted-foreground">Completed</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <Bookmark class="h-5 w-5 text-indigo-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ stats.saved_lessons }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Saved lessons
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <Award class="h-5 w-5 text-amber-600" />
                    <div>
                        <p class="text-xl font-semibold">
                            {{ stats.certificates }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Certificates
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <Clock3 class="h-5 w-5 text-teal-600" />
                    <h2 class="text-base font-semibold">Continue Watching</h2>
                </div>

                <div v-if="continue_watching.length" class="space-y-3">
                    <Link
                        v-for="item in continue_watching"
                        :key="item.id"
                        :href="item.lesson_url || '#'"
                        class="block rounded-md border p-4 transition hover:border-slate-400"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ item.lesson_title || 'Untitled lesson' }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ item.course_title }}
                                </p>
                            </div>
                            <span class="text-sm font-semibold">
                                {{ item.progress_percent }}%
                            </span>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-teal-600"
                                :style="{
                                    width: `${Math.min(item.progress_percent, 100)}%`,
                                }"
                            />
                        </div>
                    </Link>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Start a lesson and it will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <BookOpenCheck class="h-5 w-5 text-slate-700" />
                    <h2 class="text-base font-semibold">Courses</h2>
                </div>

                <div v-if="enrollments.length" class="space-y-3">
                    <Link
                        v-for="enrollment in enrollments"
                        :key="enrollment.id"
                        :href="
                            enrollment.last_lesson_url ||
                            enrollment.course_url ||
                            '#'
                        "
                        class="block rounded-md border p-4 transition hover:border-slate-400"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{
                                        enrollment.course_title ||
                                        'Untitled course'
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        enrollment.last_lesson_title ||
                                        enrollment.status
                                    }}
                                </p>
                            </div>
                            <span class="text-sm font-semibold">
                                {{ enrollment.progress_percent }}%
                            </span>
                        </div>
                    </Link>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Purchased and started courses will appear here.
                </p>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <Bookmark class="h-5 w-5 text-indigo-600" />
                    <h2 class="text-base font-semibold">Saved Lessons</h2>
                </div>

                <div v-if="bookmarks.length" class="space-y-3">
                    <Link
                        v-for="bookmark in bookmarks"
                        :key="bookmark.id"
                        :href="bookmark.lesson_url || '#'"
                        class="block rounded-md border p-3 text-sm transition hover:border-slate-400"
                    >
                        <p class="font-medium">
                            {{ bookmark.label || bookmark.lesson_title }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ bookmark.course_title }}
                        </p>
                    </Link>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Saved lessons will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-slate-700" />
                    <h2 class="text-base font-semibold">Recent Notes</h2>
                </div>

                <div v-if="notes.length" class="space-y-3">
                    <Link
                        v-for="note in notes"
                        :key="note.id"
                        :href="note.lesson_url || '#'"
                        class="block rounded-md border p-3 text-sm transition hover:border-slate-400"
                    >
                        <p class="line-clamp-2 leading-6">{{ note.body }}</p>
                        <p class="mt-1 text-muted-foreground">
                            {{ note.lesson_title }}
                        </p>
                    </Link>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Lesson notes will appear here.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <Award class="h-5 w-5 text-amber-600" />
                    <h2 class="text-base font-semibold">Certificates</h2>
                </div>

                <div v-if="certificates.length" class="space-y-3">
                    <Link
                        v-for="certificate in certificates"
                        :key="certificate.id"
                        :href="certificate.verification_url"
                        class="block rounded-md border p-3 text-sm transition hover:border-slate-400"
                    >
                        <p class="font-medium">
                            {{ certificate.course_title }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ certificate.certificate_number }}
                        </p>
                    </Link>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Completed course certificates will appear here.
                </p>
            </section>
        </div>

        <div v-if="!continue_watching.length" class="hidden">
            <PlayCircle class="h-4 w-4" />
        </div>
    </div>
</template>
