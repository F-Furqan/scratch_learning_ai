<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookMarked,
    CalendarClock,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FileQuestion,
    LockKeyhole,
    MessageSquare,
    NotebookPen,
    PlayCircle,
    Send,
    ShieldAlert,
    ThumbsUp,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';

import RichContent from '@/components/public/RichContent.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type { CourseDetail, LessonDetail, SeoPayload } from '@/types';

const props = defineProps<{
    course: CourseDetail;
    lesson: LessonDetail;
    seo: SeoPayload;
}>();

const activeTab = ref<'overview' | 'notes' | 'faq'>('overview');
const answerBodies = reactive<Record<number, string>>({});

const progressForm = useForm({
    progress_seconds: props.lesson.learning.progress?.duration_seconds || 1,
    duration_seconds: props.lesson.learning.progress?.duration_seconds || 1,
    completed: true,
});

const bookmarkForm = useForm({
    label: props.lesson.title,
    saved: true,
});

const noteForm = useForm({
    body: '',
    is_private: true,
});

const questionForm = useForm({
    title: '',
    body: '',
});

const progressPercent = computed(
    () => props.lesson.learning.progress?.progress_percent || 0,
);

const sectionLabel = computed(() => {
    const section = props.lesson.section as {
        name?: string;
        title?: string;
    } | null;

    return section?.name || section?.title || 'Lesson';
});

const watchPoster = computed(
    () =>
        props.course.thumbnail?.url ||
        '/brand/scratch-learning-watch-poster.svg',
);

const lessonIndex = computed(() => {
    const lessons = props.course.sections.flatMap((section) => section.lessons);
    const index = lessons.findIndex((item) => item.id === props.lesson.id);

    return {
        current: index >= 0 ? index + 1 : 1,
        total: lessons.length || props.course.lesson_count,
    };
});

const watchTabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'notes', label: 'Notes' },
    { id: 'faq', label: 'FAQ' },
] as const;

function markComplete() {
    if (!props.lesson.learning.actions.progress_url) {
        return;
    }

    progressForm.post(props.lesson.learning.actions.progress_url, {
        preserveScroll: true,
    });
}

function saveLesson() {
    if (!props.lesson.learning.actions.bookmark_url) {
        return;
    }

    bookmarkForm.post(props.lesson.learning.actions.bookmark_url, {
        preserveScroll: true,
    });
}

function addNote() {
    if (!props.lesson.learning.actions.note_url) {
        return;
    }

    noteForm.post(props.lesson.learning.actions.note_url, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
}

function askQuestion() {
    if (!props.lesson.community.actions.question_url) {
        return;
    }

    questionForm.post(props.lesson.community.actions.question_url, {
        preserveScroll: true,
        onSuccess: () => questionForm.reset('title', 'body'),
    });
}

function addAnswer(questionId: number, url: string | null) {
    if (!url || !answerBodies[questionId]) {
        return;
    }

    router.post(
        url,
        { body: answerBodies[questionId] },
        {
            preserveScroll: true,
            onSuccess: () => {
                answerBodies[questionId] = '';
            },
        },
    );
}

function postAction(url: string | null, payload: Record<string, string> = {}) {
    if (!url) {
        return;
    }

    router.post(url, payload, {
        preserveScroll: true,
    });
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="sl-breadcrumb text-center">
        <div class="sl-container">
            <h1 class="mx-auto">Course watch</h1>
            <nav aria-label="breadcrumb" class="sl-watch-crumbs">
                <Link href="/">Home</Link>
                <span>/</span>
                <span>Course watch</span>
            </nav>
        </div>
    </section>

    <section class="sl-watch content pt-0">
        <div class="sl-watch-fluid">
            <div class="course-watch-section">
                <div class="sl-watch-grid">
                    <aside class="sl-watch-left border-end">
                        <div class="progress-overview-section">
                            <div class="mb-4">
                                <Link :href="course.url" class="back-to-course">
                                    <ArrowLeft class="h-4 w-4" />
                                    Back to Course
                                </Link>
                            </div>

                            <h3>{{ course.title }}</h3>

                            <div class="mb-4">
                                <p class="mb-1">
                                    {{ progressPercent }}% Complete
                                </p>
                                <div
                                    class="progress progress-xs mb-2"
                                    role="progressbar"
                                    :aria-valuenow="progressPercent"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="progress-bar bg-success"
                                        :style="{
                                            width: `${progressPercent}%`,
                                        }"
                                    />
                                </div>
                                <span class="fw-medium">
                                    Lesson {{ lessonIndex.current }} of
                                    {{ lessonIndex.total }}
                                </span>
                            </div>

                            <div
                                class="accordions-items-seperate"
                                id="accordionSpacingExample"
                            >
                                <details
                                    v-for="(
                                        section, sectionIndex
                                    ) in course.sections"
                                    :key="section.id"
                                    class="accordion-item"
                                    :open="
                                        section.lessons.some(
                                            (item) => item.id === lesson.id,
                                        ) || sectionIndex === 0
                                    "
                                >
                                    <summary class="accordion-button collapsed">
                                        <div>
                                            <span class="d-block mb-1">
                                                Section {{ sectionIndex + 1 }}
                                            </span>
                                            <h6 class="mb-0">
                                                {{ section.title }}
                                            </h6>
                                        </div>
                                    </summary>

                                    <div
                                        class="accordion-collapse show collapse"
                                    >
                                        <div class="accordion-body">
                                            <Link
                                                v-for="(
                                                    item, itemIndex
                                                ) in section.lessons"
                                                :key="item.id"
                                                :href="item.url"
                                                class="sl-watch-lesson-row"
                                                :class="{
                                                    'is-current':
                                                        item.id === lesson.id,
                                                }"
                                            >
                                                <div
                                                    class="sl-watch-lesson-title"
                                                >
                                                    <span class="d-flex">
                                                        <PlayCircle
                                                            v-if="
                                                                !item.is_locked
                                                            "
                                                            class="text-success fs-24 me-1 h-6 w-6"
                                                        />
                                                        <LockKeyhole
                                                            v-else
                                                            class="text-warning fs-24 me-1 h-6 w-6"
                                                        />
                                                    </span>
                                                    <p
                                                        class="accordian-content mb-0"
                                                    >
                                                        {{ item.title }}
                                                    </p>
                                                </div>
                                                <span>
                                                    {{
                                                        itemIndex === 0
                                                            ? '2m 10s'
                                                            : '5m 20s'
                                                    }}
                                                </span>
                                            </Link>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </aside>

                    <article class="sl-watch-right">
                        <div class="course-watch-content">
                            <div class="position-relative video-btn">
                                <a
                                    v-if="lesson.video_url && !lesson.is_locked"
                                    :href="lesson.video_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label="Open lesson video"
                                >
                                    <img
                                        class="img-fluid"
                                        :src="watchPoster"
                                        alt="Course lesson preview"
                                    />
                                    <span class="play-icon">
                                        <PlayCircle class="h-7 w-7" />
                                    </span>
                                </a>
                                <div v-else class="sl-watch-preview-locked">
                                    <img
                                        class="img-fluid"
                                        :src="watchPoster"
                                        alt="Course lesson preview"
                                    />
                                    <span class="play-icon">
                                        <LockKeyhole
                                            v-if="lesson.is_locked"
                                            class="h-6 w-6"
                                        />
                                        <PlayCircle v-else class="h-7 w-7" />
                                    </span>
                                </div>
                            </div>

                            <div class="sl-watch-actions">
                                <button
                                    type="button"
                                    :disabled="
                                        !lesson.learning.actions.progress_url ||
                                        progressForm.processing ||
                                        lesson.is_locked
                                    "
                                    class="sl-btn sl-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="markComplete"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                    Mark complete
                                </button>
                                <button
                                    type="button"
                                    :disabled="
                                        !lesson.learning.actions.bookmark_url ||
                                        bookmarkForm.processing ||
                                        lesson.is_locked
                                    "
                                    class="sl-btn sl-btn-outline disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="saveLesson"
                                >
                                    <BookMarked class="h-4 w-4" />
                                    {{
                                        lesson.learning.bookmark
                                            ? 'Saved'
                                            : 'Save lesson'
                                    }}
                                </button>
                                <span
                                    class="sl-access-badge"
                                    :class="
                                        lesson.is_locked
                                            ? 'is-locked'
                                            : 'is-open'
                                    "
                                >
                                    <LockKeyhole
                                        v-if="lesson.is_locked"
                                        class="h-4 w-4"
                                    />
                                    <PlayCircle v-else class="h-4 w-4" />
                                    {{
                                        lesson.is_locked
                                            ? 'Access required'
                                            : 'Open lesson'
                                    }}
                                </span>
                            </div>

                            <ul
                                class="nav-tabs nav-justified nav-style-1 d-sm-flex d-block mb-4 border-0"
                                role="tablist"
                            >
                                <li
                                    v-for="tab in watchTabs"
                                    :key="tab.id"
                                    class="nav-item"
                                    :class="{ active: activeTab === tab.id }"
                                >
                                    <button
                                        type="button"
                                        class="btn nav-link"
                                        :class="{
                                            active: activeTab === tab.id,
                                        }"
                                        @click="activeTab = tab.id"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div
                                    v-if="activeTab === 'overview'"
                                    class="tab-pane active show"
                                    role="tabpanel"
                                >
                                    <div
                                        v-if="lesson.is_locked"
                                        class="sl-locked-alert"
                                    >
                                        <LockKeyhole
                                            class="mt-1 h-5 w-5 text-[#9a5a00]"
                                        />
                                        <div>
                                            <h6>Access required</h6>
                                            <p>
                                                {{
                                                    lesson.preview ||
                                                    'This lesson is part of a protected paid course. Enroll or purchase access to continue watching.'
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    lesson.learning.drip
                                                        .available_at
                                                "
                                                class="sl-drip-date"
                                            >
                                                <CalendarClock
                                                    class="h-4 w-4"
                                                />
                                                Opens
                                                {{
                                                    new Date(
                                                        lesson.learning.drip
                                                            .available_at,
                                                    ).toLocaleDateString()
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <h6 class="fs-18 fw-semibold mb-1">
                                            About this course
                                        </h6>
                                        <RichContent
                                            v-if="lesson.content"
                                            :html="lesson.content"
                                        />
                                        <p v-else>
                                            {{
                                                lesson.preview ||
                                                course.short_description ||
                                                'This lesson is being prepared by the editorial team.'
                                            }}
                                        </p>
                                    </div>

                                    <div class="mb-4">
                                        <h6 class="fs-18 fw-semibold mb-2">
                                            Description
                                        </h6>
                                        <p>
                                            {{
                                                course.description ||
                                                'Follow the course curriculum, complete lessons, and use notes and Q&A to support your learning progress.'
                                            }}
                                        </p>
                                        <p class="mb-1">
                                            Current lesson:
                                            <strong>{{ lesson.title }}</strong>
                                            in {{ sectionLabel }}.
                                        </p>
                                        <Link
                                            :href="course.url"
                                            class="readmore-btn"
                                        >
                                            Readmore
                                        </Link>
                                    </div>

                                    <div class="mb-4">
                                        <h6 class="fs-18 fw-semibold mb-2">
                                            What You’ll Learn
                                        </h6>
                                        <ul class="what-you-learn">
                                            <li>
                                                Work through a structured lesson
                                                sequence.
                                            </li>
                                            <li>
                                                Track progress and continue from
                                                your last activity.
                                            </li>
                                            <li>
                                                Use notes, resources, quizzes,
                                                and assignments.
                                            </li>
                                            <li>
                                                Ask lesson questions when
                                                community access is available.
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mb-0">
                                        <h6 class="fs-18 fw-semibold mb-2">
                                            Requirements
                                        </h6>
                                        <ul class="what-you-learn">
                                            <li>
                                                A Scratch Learning account for
                                                progress actions.
                                            </li>
                                            <li>
                                                Course purchase or entitlement
                                                for locked lessons.
                                            </li>
                                            <li>
                                                Time to complete practical
                                                learning tasks.
                                            </li>
                                        </ul>
                                    </div>

                                    <div
                                        v-if="
                                            !lesson.is_locked &&
                                            (lesson.learning.resources.length ||
                                                lesson.learning.quizzes
                                                    .length ||
                                                lesson.learning.assignments
                                                    .length)
                                        "
                                        class="sl-watch-side-grid"
                                    >
                                        <section
                                            v-if="
                                                lesson.learning.resources.length
                                            "
                                            class="sl-watch-mini-card"
                                        >
                                            <h6>
                                                <Download class="h-4 w-4" />
                                                Resources
                                            </h6>
                                            <a
                                                v-for="resource in lesson
                                                    .learning.resources"
                                                :key="resource.id"
                                                :href="
                                                    resource.download_url ||
                                                    undefined
                                                "
                                            >
                                                {{ resource.title }}
                                            </a>
                                        </section>

                                        <section
                                            v-if="
                                                lesson.learning.quizzes.length
                                            "
                                            class="sl-watch-mini-card"
                                        >
                                            <h6>
                                                <FileQuestion class="h-4 w-4" />
                                                Quizzes
                                            </h6>
                                            <p
                                                v-for="quiz in lesson.learning
                                                    .quizzes"
                                                :key="quiz.id"
                                            >
                                                {{ quiz.title }} · Pass
                                                {{ quiz.pass_score }}%
                                            </p>
                                        </section>

                                        <section
                                            v-if="
                                                lesson.learning.assignments
                                                    .length
                                            "
                                            class="sl-watch-mini-card"
                                        >
                                            <h6>
                                                <ClipboardCheck
                                                    class="h-4 w-4"
                                                />
                                                Assignments
                                            </h6>
                                            <p
                                                v-for="assignment in lesson
                                                    .learning.assignments"
                                                :key="assignment.id"
                                            >
                                                {{ assignment.title }}
                                            </p>
                                        </section>
                                    </div>
                                </div>

                                <div
                                    v-if="activeTab === 'notes'"
                                    class="tab-pane active show"
                                    role="tabpanel"
                                >
                                    <div class="mb-0">
                                        <h6 class="fs-18 fw-semibold mb-1">
                                            Notes
                                        </h6>
                                        <p>
                                            Save private notes against this
                                            lesson while you watch.
                                        </p>
                                        <textarea
                                            v-model="noteForm.body"
                                            rows="5"
                                            class="sl-watch-input"
                                            placeholder="Write a private lesson note"
                                        />
                                        <button
                                            type="button"
                                            :disabled="
                                                !noteForm.body ||
                                                !lesson.learning.actions
                                                    .note_url ||
                                                noteForm.processing ||
                                                lesson.is_locked
                                            "
                                            class="sl-btn sl-btn-primary mt-3 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="addNote"
                                        >
                                            <NotebookPen class="h-4 w-4" />
                                            Add note
                                        </button>

                                        <div
                                            v-if="lesson.learning.notes.length"
                                            class="sl-notes-list"
                                        >
                                            <p
                                                v-for="note in lesson.learning
                                                    .notes"
                                                :key="note.id"
                                            >
                                                {{ note.body }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="activeTab === 'faq'"
                                    class="tab-pane active show"
                                    role="tabpanel"
                                >
                                    <div
                                        v-if="lesson.faqs.length"
                                        class="faq-accordion"
                                    >
                                        <div class="accordions-items-seperate">
                                            <details
                                                v-for="faq in lesson.faqs"
                                                :key="faq.id"
                                                class="accordion-item"
                                            >
                                                <summary
                                                    class="accordion-button"
                                                >
                                                    {{ faq.question }}
                                                </summary>
                                                <div class="accordion-body">
                                                    <p class="mb-0">
                                                        {{ faq.answer }}
                                                    </p>
                                                </div>
                                            </details>
                                        </div>
                                    </div>

                                    <section
                                        v-if="
                                            lesson.allow_questions &&
                                            !lesson.is_locked
                                        "
                                        class="sl-watch-qna"
                                    >
                                        <div class="sl-watch-qna-head">
                                            <div>
                                                <h6
                                                    class="fs-18 fw-semibold mb-1"
                                                >
                                                    Lesson Q&A
                                                </h6>
                                                <p>
                                                    {{
                                                        lesson.community
                                                            .questions.length
                                                    }}
                                                    questions
                                                </p>
                                            </div>
                                            <MessageSquare class="h-6 w-6" />
                                        </div>

                                        <form
                                            v-if="
                                                lesson.community.actions
                                                    .question_url
                                            "
                                            class="sl-watch-form"
                                            @submit.prevent="askQuestion"
                                        >
                                            <input
                                                v-model="questionForm.title"
                                                type="text"
                                                class="sl-watch-input"
                                                placeholder="Question title"
                                            />
                                            <textarea
                                                v-model="questionForm.body"
                                                class="sl-watch-input"
                                                placeholder="Ask about this lesson"
                                            />
                                            <button
                                                type="submit"
                                                class="sl-btn sl-btn-primary w-fit"
                                                :disabled="
                                                    questionForm.processing
                                                "
                                            >
                                                <Send class="h-4 w-4" />
                                                Ask
                                            </button>
                                        </form>

                                        <div
                                            v-if="
                                                lesson.community.questions
                                                    .length
                                            "
                                            class="sl-qna-list"
                                        >
                                            <article
                                                v-for="question in lesson
                                                    .community.questions"
                                                :key="question.id"
                                            >
                                                <div
                                                    class="sl-qna-question-head"
                                                >
                                                    <div>
                                                        <h6>
                                                            {{
                                                                question.title ||
                                                                'Question'
                                                            }}
                                                        </h6>
                                                        <p>
                                                            {{
                                                                question.author
                                                                    ?.name ||
                                                                'Member'
                                                            }}
                                                        </p>
                                                    </div>
                                                    <button
                                                        v-if="
                                                            question.report_url
                                                        "
                                                        type="button"
                                                        class="sl-ghost-action"
                                                        @click="
                                                            postAction(
                                                                question.report_url,
                                                                {
                                                                    reason: 'needs_review',
                                                                },
                                                            )
                                                        "
                                                    >
                                                        <ShieldAlert
                                                            class="h-4 w-4"
                                                        />
                                                        Report
                                                    </button>
                                                </div>
                                                <p>{{ question.body }}</p>

                                                <div
                                                    v-if="
                                                        question.answers.length
                                                    "
                                                    class="sl-answer-list"
                                                >
                                                    <div
                                                        v-for="answer in question.answers"
                                                        :key="answer.id"
                                                        class="sl-answer"
                                                        :class="{
                                                            'is-accepted':
                                                                answer.is_accepted,
                                                        }"
                                                    >
                                                        <div>
                                                            <strong>
                                                                {{
                                                                    answer
                                                                        .author
                                                                        ?.name ||
                                                                    'Member'
                                                                }}
                                                            </strong>
                                                            <span
                                                                v-if="
                                                                    answer.is_accepted
                                                                "
                                                            >
                                                                Accepted
                                                            </span>
                                                        </div>
                                                        <p>{{ answer.body }}</p>
                                                        <div
                                                            class="sl-answer-actions"
                                                        >
                                                            <button
                                                                v-if="
                                                                    answer.accept_url
                                                                "
                                                                type="button"
                                                                @click="
                                                                    postAction(
                                                                        answer.accept_url,
                                                                    )
                                                                "
                                                            >
                                                                <Check
                                                                    class="h-4 w-4"
                                                                />
                                                                Accept
                                                            </button>
                                                            <button
                                                                v-if="
                                                                    answer.reaction_url
                                                                "
                                                                type="button"
                                                                @click="
                                                                    postAction(
                                                                        answer.reaction_url,
                                                                        {
                                                                            type: 'upvote',
                                                                        },
                                                                    )
                                                                "
                                                            >
                                                                <ThumbsUp
                                                                    class="h-4 w-4"
                                                                />
                                                                {{
                                                                    answer.upvotes_count
                                                                }}
                                                            </button>
                                                            <button
                                                                v-if="
                                                                    answer.report_url
                                                                "
                                                                type="button"
                                                                @click="
                                                                    postAction(
                                                                        answer.report_url,
                                                                        {
                                                                            reason: 'needs_review',
                                                                        },
                                                                    )
                                                                "
                                                            >
                                                                <ShieldAlert
                                                                    class="h-4 w-4"
                                                                />
                                                                Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <form
                                                    v-if="question.answer_url"
                                                    class="sl-watch-form"
                                                    @submit.prevent="
                                                        addAnswer(
                                                            question.id,
                                                            question.answer_url,
                                                        )
                                                    "
                                                >
                                                    <textarea
                                                        v-model="
                                                            answerBodies[
                                                                question.id
                                                            ]
                                                        "
                                                        class="sl-watch-input"
                                                        placeholder="Add an answer"
                                                    />
                                                    <button
                                                        type="submit"
                                                        class="sl-btn sl-btn-primary w-fit"
                                                    >
                                                        <Send class="h-4 w-4" />
                                                        Answer
                                                    </button>
                                                </form>
                                            </article>
                                        </div>
                                        <p v-else>No approved questions yet.</p>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
