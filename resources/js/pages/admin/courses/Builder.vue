<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    ClipboardCheck,
    ExternalLink,
    FileQuestion,
    FileText,
    GripVertical,
    HelpCircle,
    History,
    Layers3,
    LibraryBig,
    Plus,
    Save,
    SearchCheck,
    Settings2,
    ShieldCheck,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import AdminMediaPicker from '@/components/admin/AdminMediaPicker.vue';
import RichTextEditor from '@/components/admin/RichTextEditor.vue';

type Option = {
    label: string;
    value: string | number;
    url?: string | null;
    parentValue?: string | number;
};

type MediaOption = Omit<Option, 'url'> & {
    url: string | null;
};

type ItemUrls = {
    update_url: string;
    delete_url: string;
};

type SectionItem = ItemUrls & {
    id: number;
    title: string;
    description: string | null;
    sort_order: number;
    status: string;
};

type LessonItem = ItemUrls & {
    id: number;
    course_section_id: number | null;
    title: string;
    order_number: number;
    content: string | null;
    video_type: string;
    video_url: string | null;
    video_file_id: number | null;
    is_free: boolean;
    is_paid: boolean;
    preview_word_limit: number | null;
    status: string;
    seo_title: string | null;
    seo_description: string | null;
};

type ResourceItem = ItemUrls & {
    id: number;
    course_lesson_id: number | null;
    media_asset_id: number | null;
    title: string;
    description: string | null;
    type: string;
    access_level: string;
    file_path: string | null;
    external_url: string | null;
    is_downloadable: boolean;
    is_active: boolean;
    sort_order: number;
};

type FaqItem = ItemUrls & {
    id: number;
    course_lesson_id: number | null;
    question: string;
    answer: string;
    sort_order: number;
    status: string;
};

type QuestionItem = ItemUrls & {
    id: number;
    quiz_id: number;
    question: string;
    type: string;
    points: number;
    options_json: string | null;
    correct_answer_json: string;
    explanation: string | null;
    sort_order: number;
};

type QuizItem = ItemUrls & {
    id: number;
    course_lesson_id: number | null;
    title: string;
    description: string | null;
    pass_score: number;
    max_attempts: number;
    time_limit_minutes: number | null;
    is_required: boolean;
    is_active: boolean;
    sort_order: number;
    questions: QuestionItem[];
};

type AssignmentItem = ItemUrls & {
    id: number;
    course_lesson_id: number | null;
    title: string;
    instructions: string;
    pass_score: number;
    max_points: number;
    due_days_after_enrollment: number | null;
    allow_file_uploads: boolean;
    is_required: boolean;
    is_active: boolean;
    sort_order: number;
};

type PriceItem = ItemUrls & {
    id: number;
    name: string;
    paddle_price_id: string | null;
    billing_interval: string;
    is_recurring: boolean;
    currency: string;
    amount: number;
    trial_days: number | null;
    is_active: boolean;
};

type ApprovalItem = {
    id: number;
    decision: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    actor: string | null;
    created_at: string | null;
};

type CoursePayload = {
    id: number;
    title: string;
    slug: string;
    course_category_id: number | null;
    course_subcategory_id: number | null;
    created_by: number | null;
    thumbnail_media_id: number | null;
    short_description: string | null;
    description: string | null;
    intro_video_url: string | null;
    level: string | null;
    language: string;
    price: string | number;
    is_free: boolean;
    status: string;
    published_at: string | null;
    admin_notes: string | null;
    rejection_reason: string | null;
    seo_title: string | null;
    seo_description: string | null;
    seo_image: string | null;
    canonical_url: string | null;
    schema: string | null;
    ownership_video_media_id: number | null;
    ownership_video_url: string | null;
    ownership_statement: string | null;
    ownership_confirmed_at: string | null;
    ownership_complete: boolean;
    creator_name: string | null;
};

type ProductPayload = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    status: string;
    paddle_product_id: string | null;
    tax_category: string | null;
};

const props = defineProps<{
    course: CoursePayload;
    sections: SectionItem[];
    lessons: LessonItem[];
    resources: ResourceItem[];
    faqs: FaqItem[];
    quizzes: QuizItem[];
    assignments: AssignmentItem[];
    pricing: {
        is_free: boolean;
        price: string | number;
        product: ProductPayload | null;
        prices: PriceItem[];
    };
    approval_history: ApprovalItem[];
    readiness_issues: string[];
    options: {
        categories: Option[];
        subcategories: Option[];
        creators: Option[];
        media: MediaOption[];
        publish_statuses: Option[];
        video_types: Option[];
        resource_access: Option[];
        question_types: Option[];
        product_types: Option[];
        product_statuses: Option[];
        billing_intervals: Option[];
    };
    urls: {
        index: string;
        update: string;
        ownership: string;
        publishing: string;
        product: string;
        items: string;
        reorder: string;
        public: string | null;
    };
}>();

const tabs = [
    { id: 'information', label: 'Information', icon: Settings2 },
    { id: 'curriculum', label: 'Curriculum', icon: Layers3 },
    { id: 'resources', label: 'Resources & FAQs', icon: LibraryBig },
    { id: 'assessment', label: 'Assessments', icon: ClipboardCheck },
    { id: 'pricing', label: 'Pricing & access', icon: BadgeDollarSign },
    { id: 'seo', label: 'SEO', icon: SearchCheck },
    { id: 'ownership', label: 'Ownership', icon: ShieldCheck },
    { id: 'publishing', label: 'Publishing', icon: History },
] as const;

const activeTab = ref<(typeof tabs)[number]['id']>('information');
const orderedSections = ref([...props.sections]);
const orderedLessons = ref([...props.lessons]);
const draggedItem = ref<{ type: 'sections' | 'lessons'; id: number } | null>(
    null,
);

watch(
    () => props.sections,
    (value) => (orderedSections.value = [...value]),
);
watch(
    () => props.lessons,
    (value) => (orderedLessons.value = [...value]),
);

const courseForm = useForm({
    title: props.course.title,
    course_category_id: props.course.course_category_id,
    course_subcategory_id: props.course.course_subcategory_id,
    created_by: props.course.created_by,
    thumbnail_media_id: props.course.thumbnail_media_id,
    short_description: props.course.short_description || '',
    description: props.course.description || '',
    intro_video_url: props.course.intro_video_url || '',
    level: props.course.level || '',
    language: props.course.language || 'en',
    seo_title: props.course.seo_title || '',
    seo_description: props.course.seo_description || '',
    seo_image: props.course.seo_image || '',
    canonical_url: props.course.canonical_url || '',
    schema: props.course.schema || '',
});

const sectionForm = useForm({
    title: '',
    description: '',
    sort_order: 0,
    status: 'draft',
});
const editingSection = ref<SectionItem | null>(null);
const lessonForm = useForm({
    course_section_id: null as number | null,
    title: '',
    order_number: 0,
    content: '',
    video_type: 'none',
    video_url: '',
    video_file_id: null as number | null,
    is_free: false,
    is_paid: false,
    preview_word_limit: null as number | null,
    status: 'draft',
    seo_title: '',
    seo_description: '',
});
const editingLesson = ref<LessonItem | null>(null);

const resourceForm = useForm({
    course_lesson_id: null as number | null,
    media_asset_id: null as number | null,
    title: '',
    description: '',
    type: 'file',
    access_level: 'enrolled',
    file_path: '',
    external_url: '',
    is_downloadable: true,
    is_active: true,
    sort_order: 0,
});
const editingResource = ref<ResourceItem | null>(null);
const faqForm = useForm({
    course_lesson_id: null as number | null,
    question: '',
    answer: '',
    sort_order: 0,
    status: 'draft',
});
const editingFaq = ref<FaqItem | null>(null);

const quizForm = useForm({
    course_lesson_id: null as number | null,
    title: '',
    description: '',
    pass_score: 70,
    max_attempts: 3,
    time_limit_minutes: null as number | null,
    is_required: true,
    is_active: true,
    sort_order: 0,
});
const editingQuiz = ref<QuizItem | null>(null);
const questionForm = useForm({
    quiz_id: null as number | null,
    question: '',
    type: 'multiple_choice',
    points: 1,
    options_json: '[\n  "Option A",\n  "Option B"\n]',
    correct_answer_json: '"Option A"',
    explanation: '',
    sort_order: 0,
});
const editingQuestion = ref<QuestionItem | null>(null);
const assignmentForm = useForm({
    course_lesson_id: null as number | null,
    title: '',
    instructions: '',
    pass_score: 70,
    max_points: 100,
    due_days_after_enrollment: null as number | null,
    allow_file_uploads: true,
    is_required: true,
    is_active: true,
    sort_order: 0,
});
const editingAssignment = ref<AssignmentItem | null>(null);

const productForm = useForm({
    is_free: props.pricing.is_free,
    price: Number(props.pricing.price || 0),
    name: props.pricing.product?.name || props.course.title,
    description: props.pricing.product?.description || '',
    type: props.pricing.product?.type || 'course',
    status: props.pricing.product?.status || 'active',
    paddle_product_id: props.pricing.product?.paddle_product_id || '',
    tax_category: props.pricing.product?.tax_category || '',
});
const priceForm = useForm({
    name: '',
    paddle_price_id: '',
    billing_interval: 'one_time',
    is_recurring: false,
    currency: 'USD',
    amount: 0,
    trial_days: null as number | null,
    is_active: true,
});
const editingPrice = ref<PriceItem | null>(null);

const ownershipForm = useForm({
    ownership_video_media_id: props.course.ownership_video_media_id,
    ownership_video_url: props.course.ownership_video_url || '',
    ownership_statement: props.course.ownership_statement || '',
    confirmed: false,
});
const publishingForm = useForm({
    status: props.course.status,
    admin_notes: props.course.admin_notes || '',
    rejection_reason: props.course.rejection_reason || '',
});

const filteredSubcategories = computed(() =>
    props.options.subcategories.filter(
        (option) =>
            String(option.parentValue) ===
            String(courseForm.course_category_id),
    ),
);

watch(
    () => courseForm.course_category_id,
    () => {
        if (
            !filteredSubcategories.value.some(
                (item) =>
                    String(item.value) ===
                    String(courseForm.course_subcategory_id),
            )
        ) {
            courseForm.course_subcategory_id = null;
        }
    },
);

const inputClass =
    'min-h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20';
const textareaClass = `${inputClass} min-h-24 resize-y`;

function itemStoreUrl(kind: string) {
    return props.urls.items.replace('__kind__', kind);
}

function saveCourse() {
    courseForm.patch(props.urls.update, { preserveScroll: true });
}

function clearSection() {
    editingSection.value = null;
    sectionForm.reset();
    sectionForm.status = 'draft';
}

function editSection(item: SectionItem) {
    editingSection.value = item;
    sectionForm.title = item.title;
    sectionForm.description = item.description || '';
    sectionForm.sort_order = item.sort_order;
    sectionForm.status = item.status;
}

function saveSection() {
    const method = editingSection.value ? 'patch' : 'post';
    const url = editingSection.value?.update_url || itemStoreUrl('sections');
    sectionForm[method](url, { preserveScroll: true, onSuccess: clearSection });
}

function clearLesson() {
    editingLesson.value = null;
    lessonForm.reset();
    lessonForm.video_type = 'none';
    lessonForm.status = 'draft';
}

function editLesson(item: LessonItem) {
    activeTab.value = 'curriculum';
    editingLesson.value = item;
    lessonForm.course_section_id = item.course_section_id;
    lessonForm.title = item.title;
    lessonForm.order_number = item.order_number;
    lessonForm.content = item.content || '';
    lessonForm.video_type = item.video_type;
    lessonForm.video_url = item.video_url || '';
    lessonForm.video_file_id = item.video_file_id;
    lessonForm.is_free = item.is_free;
    lessonForm.is_paid = item.is_paid;
    lessonForm.preview_word_limit = item.preview_word_limit;
    lessonForm.status = item.status;
    lessonForm.seo_title = item.seo_title || '';
    lessonForm.seo_description = item.seo_description || '';
}

function saveLesson() {
    const method = editingLesson.value ? 'patch' : 'post';
    const url = editingLesson.value?.update_url || itemStoreUrl('lessons');
    lessonForm[method](url, { preserveScroll: true, onSuccess: clearLesson });
}

function clearResource() {
    editingResource.value = null;
    resourceForm.reset();
    resourceForm.type = 'file';
    resourceForm.access_level = 'enrolled';
    resourceForm.is_downloadable = true;
    resourceForm.is_active = true;
}

function editResource(item: ResourceItem) {
    editingResource.value = item;
    Object.assign(resourceForm, {
        course_lesson_id: item.course_lesson_id,
        media_asset_id: item.media_asset_id,
        title: item.title,
        description: item.description || '',
        type: item.type,
        access_level: item.access_level,
        file_path: item.file_path || '',
        external_url: item.external_url || '',
        is_downloadable: item.is_downloadable,
        is_active: item.is_active,
        sort_order: item.sort_order,
    });
}

function saveResource() {
    const method = editingResource.value ? 'patch' : 'post';
    const url = editingResource.value?.update_url || itemStoreUrl('resources');
    resourceForm[method](url, {
        preserveScroll: true,
        onSuccess: clearResource,
    });
}

function clearFaq() {
    editingFaq.value = null;
    faqForm.reset();
    faqForm.status = 'draft';
}

function editFaq(item: FaqItem) {
    editingFaq.value = item;
    Object.assign(faqForm, {
        course_lesson_id: item.course_lesson_id,
        question: item.question,
        answer: item.answer,
        sort_order: item.sort_order,
        status: item.status,
    });
}

function saveFaq() {
    const method = editingFaq.value ? 'patch' : 'post';
    const url = editingFaq.value?.update_url || itemStoreUrl('faqs');
    faqForm[method](url, { preserveScroll: true, onSuccess: clearFaq });
}

function clearQuiz() {
    editingQuiz.value = null;
    quizForm.reset();
    quizForm.pass_score = 70;
    quizForm.max_attempts = 3;
    quizForm.is_required = true;
    quizForm.is_active = true;
}

function editQuiz(item: QuizItem) {
    editingQuiz.value = item;
    Object.assign(quizForm, {
        course_lesson_id: item.course_lesson_id,
        title: item.title,
        description: item.description || '',
        pass_score: item.pass_score,
        max_attempts: item.max_attempts,
        time_limit_minutes: item.time_limit_minutes,
        is_required: item.is_required,
        is_active: item.is_active,
        sort_order: item.sort_order,
    });
}

function saveQuiz() {
    const method = editingQuiz.value ? 'patch' : 'post';
    const url = editingQuiz.value?.update_url || itemStoreUrl('quizzes');
    quizForm[method](url, { preserveScroll: true, onSuccess: clearQuiz });
}

function clearQuestion() {
    editingQuestion.value = null;
    questionForm.reset();
    questionForm.type = 'multiple_choice';
    questionForm.points = 1;
    questionForm.options_json = '[\n  "Option A",\n  "Option B"\n]';
    questionForm.correct_answer_json = '"Option A"';
}

function editQuestion(item: QuestionItem) {
    editingQuestion.value = item;
    Object.assign(questionForm, {
        quiz_id: item.quiz_id,
        question: item.question,
        type: item.type,
        points: item.points,
        options_json: item.options_json || '',
        correct_answer_json: item.correct_answer_json,
        explanation: item.explanation || '',
        sort_order: item.sort_order,
    });
}

function saveQuestion() {
    const method = editingQuestion.value ? 'patch' : 'post';
    const url =
        editingQuestion.value?.update_url || itemStoreUrl('quiz-questions');
    questionForm[method](url, {
        preserveScroll: true,
        onSuccess: clearQuestion,
    });
}

function clearAssignment() {
    editingAssignment.value = null;
    assignmentForm.reset();
    assignmentForm.pass_score = 70;
    assignmentForm.max_points = 100;
    assignmentForm.allow_file_uploads = true;
    assignmentForm.is_required = true;
    assignmentForm.is_active = true;
}

function editAssignment(item: AssignmentItem) {
    editingAssignment.value = item;
    Object.assign(assignmentForm, {
        course_lesson_id: item.course_lesson_id,
        title: item.title,
        instructions: item.instructions,
        pass_score: item.pass_score,
        max_points: item.max_points,
        due_days_after_enrollment: item.due_days_after_enrollment,
        allow_file_uploads: item.allow_file_uploads,
        is_required: item.is_required,
        is_active: item.is_active,
        sort_order: item.sort_order,
    });
}

function saveAssignment() {
    const method = editingAssignment.value ? 'patch' : 'post';
    const url =
        editingAssignment.value?.update_url || itemStoreUrl('assignments');
    assignmentForm[method](url, {
        preserveScroll: true,
        onSuccess: clearAssignment,
    });
}

function clearPrice() {
    editingPrice.value = null;
    priceForm.reset();
    priceForm.billing_interval = 'one_time';
    priceForm.currency = 'USD';
    priceForm.is_active = true;
}

function editPrice(item: PriceItem) {
    editingPrice.value = item;
    Object.assign(priceForm, {
        name: item.name,
        paddle_price_id: item.paddle_price_id || '',
        billing_interval: item.billing_interval,
        is_recurring: item.is_recurring,
        currency: item.currency,
        amount: item.amount,
        trial_days: item.trial_days,
        is_active: item.is_active,
    });
}

function savePrice() {
    const method = editingPrice.value ? 'patch' : 'post';
    const url = editingPrice.value?.update_url || itemStoreUrl('prices');
    priceForm[method](url, { preserveScroll: true, onSuccess: clearPrice });
}

function deleteItem(url: string, label: string) {
    if (window.confirm(`Delete ${label}?`)) {
        router.delete(url, { preserveScroll: true });
    }
}

function dropItem(type: 'sections' | 'lessons', targetId: number) {
    if (
        !draggedItem.value ||
        draggedItem.value.type !== type ||
        draggedItem.value.id === targetId
    ) {
        return;
    }

    if (type === 'sections') {
        moveAndPersist(orderedSections.value, type, targetId);
    } else {
        moveAndPersist(orderedLessons.value, type, targetId);
    }

    draggedItem.value = null;
}

function moveAndPersist<T extends { id: number }>(
    list: T[],
    type: 'sections' | 'lessons',
    targetId: number,
) {
    const sourceIndex = list.findIndex(
        (item) => item.id === draggedItem.value?.id,
    );
    const targetIndex = list.findIndex((item) => item.id === targetId);

    if (sourceIndex < 0 || targetIndex < 0) {
        return;
    }

    const [moved] = list.splice(sourceIndex, 1);

    list.splice(targetIndex, 0, moved);
    router.patch(
        props.urls.reorder,
        { type, ids: list.map((item) => item.id) },
        { preserveScroll: true },
    );
}

function lessonTitle(id: number | null) {
    return (
        props.lessons.find((lesson) => lesson.id === id)?.title ||
        'Course level'
    );
}

function sectionTitle(id: number | null) {
    return (
        props.sections.find((section) => section.id === id)?.title ||
        'No section'
    );
}

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not recorded';
}
</script>

<template>
    <Head :title="`${course.title} builder`" />

    <div class="flex min-w-0 flex-1 flex-col bg-muted/20">
        <header
            class="sticky top-0 z-20 border-b bg-background/95 backdrop-blur"
        >
            <div
                class="flex min-h-16 flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="urls.index"
                        class="grid size-9 shrink-0 place-items-center rounded-md border hover:bg-muted"
                        title="Back to courses"
                    >
                        <X class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="truncate text-lg font-semibold">
                                {{ course.title }}
                            </h1>
                            <span
                                class="rounded border px-2 py-0.5 text-xs font-medium capitalize"
                                >{{ course.status.replaceAll('_', ' ') }}</span
                            >
                        </div>
                        <p class="truncate text-xs text-muted-foreground">
                            /{{ course.slug }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        v-if="urls.public"
                        :href="urls.public"
                        target="_blank"
                        class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <ExternalLink class="size-4" /> Preview
                    </a>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                        :disabled="courseForm.processing"
                        @click="saveCourse"
                    >
                        <Save class="size-4" /> Save
                    </button>
                </div>
            </div>
        </header>

        <div
            class="grid min-h-[calc(100vh-8rem)] min-w-0 lg:grid-cols-[15rem_minmax(0,1fr)]"
        >
            <aside class="border-b bg-background p-3 lg:border-r lg:border-b-0">
                <nav
                    class="grid grid-cols-2 gap-1 sm:grid-cols-4 lg:sticky lg:top-20 lg:grid-cols-1"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="flex min-h-10 items-center gap-2 rounded-md px-3 text-left text-sm font-medium transition-colors"
                        :class="
                            activeTab === tab.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="activeTab = tab.id"
                    >
                        <component :is="tab.icon" class="size-4 shrink-0" />
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </nav>
            </aside>

            <main class="min-w-0 p-4 md:p-6 xl:p-8">
                <div class="mx-auto max-w-6xl">
                    <section
                        v-if="activeTab === 'information'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Course information
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Core catalog identity and presentation.
                            </p>
                        </div>
                        <form class="space-y-5" @submit.prevent="saveCourse">
                            <div class="grid gap-4 md:grid-cols-2">
                                <label
                                    class="grid gap-2 text-sm font-medium md:col-span-2"
                                    >Title<input
                                        v-model="courseForm.title"
                                        required
                                        :class="inputClass"
                                    /><span
                                        v-if="courseForm.errors.title"
                                        class="text-xs text-destructive"
                                        >{{ courseForm.errors.title }}</span
                                    ></label
                                >
                                <label class="grid gap-2 text-sm font-medium"
                                    >Category<select
                                        v-model="courseForm.course_category_id"
                                        :class="inputClass"
                                    >
                                        <option :value="null">
                                            Uncategorized
                                        </option>
                                        <option
                                            v-for="option in options.categories"
                                            :key="option.value"
                                            :value="Number(option.value)"
                                        >
                                            {{ option.label }}
                                        </option></select
                                    ><span
                                        v-if="
                                            courseForm.errors.course_category_id
                                        "
                                        class="text-xs text-destructive"
                                        >{{
                                            courseForm.errors.course_category_id
                                        }}</span
                                    ></label
                                >
                                <label class="grid gap-2 text-sm font-medium"
                                    >Subcategory<select
                                        v-model="
                                            courseForm.course_subcategory_id
                                        "
                                        :class="inputClass"
                                    >
                                        <option :value="null">
                                            No subcategory
                                        </option>
                                        <option
                                            v-for="option in filteredSubcategories"
                                            :key="option.value"
                                            :value="Number(option.value)"
                                        >
                                            {{ option.label }}
                                        </option></select
                                    ><span
                                        v-if="
                                            courseForm.errors
                                                .course_subcategory_id
                                        "
                                        class="text-xs text-destructive"
                                        >{{
                                            courseForm.errors
                                                .course_subcategory_id
                                        }}</span
                                    ></label
                                >
                                <label class="grid gap-2 text-sm font-medium"
                                    >Creator<select
                                        v-model="courseForm.created_by"
                                        :class="inputClass"
                                    >
                                        <option :value="null">
                                            No creator
                                        </option>
                                        <option
                                            v-for="option in options.creators"
                                            :key="option.value"
                                            :value="Number(option.value)"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select></label
                                >
                                <label class="grid gap-2 text-sm font-medium"
                                    >Language<input
                                        v-model="courseForm.language"
                                        required
                                        maxlength="16"
                                        :class="inputClass"
                                /></label>
                                <label class="grid gap-2 text-sm font-medium"
                                    >Level<input
                                        v-model="courseForm.level"
                                        :class="inputClass"
                                        placeholder="Beginner, intermediate, advanced"
                                /></label>
                                <label class="grid gap-2 text-sm font-medium"
                                    >Intro video URL<input
                                        v-model="courseForm.intro_video_url"
                                        type="url"
                                        :class="inputClass"
                                /></label>
                                <label
                                    class="grid gap-2 text-sm font-medium md:col-span-2"
                                    >Short description<textarea
                                        v-model="courseForm.short_description"
                                        :class="textareaClass"
                                    />
                                </label>
                                <label
                                    class="grid gap-2 text-sm font-medium md:col-span-2"
                                    >Description<textarea
                                        v-model="courseForm.description"
                                        :class="`${textareaClass} min-h-40`"
                                    />
                                </label>
                                <div
                                    class="grid gap-2 text-sm font-medium md:col-span-2"
                                >
                                    Thumbnail<AdminMediaPicker
                                        v-model="courseForm.thumbnail_media_id"
                                        :assets="options.media"
                                    />
                                </div>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                                :disabled="courseForm.processing"
                            >
                                <Save class="size-4" /> Save information
                            </button>
                        </form>
                    </section>

                    <section
                        v-else-if="activeTab === 'curriculum'"
                        class="space-y-8"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">Curriculum</h2>
                            <p class="text-sm text-muted-foreground">
                                Sections, lessons, content, video, and ordering.
                            </p>
                        </div>
                        <div
                            class="grid gap-6 xl:grid-cols-[minmax(18rem,0.75fr)_minmax(0,1.5fr)]"
                        >
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold">Sections</h3>
                                    <span class="text-xs text-muted-foreground"
                                        >{{ sections.length }} total</span
                                    >
                                </div>
                                <form
                                    class="space-y-3 border-y py-4"
                                    @submit.prevent="saveSection"
                                >
                                    <input
                                        v-model="sectionForm.title"
                                        required
                                        :class="inputClass"
                                        placeholder="Section title"
                                    />
                                    <textarea
                                        v-model="sectionForm.description"
                                        :class="textareaClass"
                                        placeholder="Description"
                                    />
                                    <div class="grid grid-cols-2 gap-3">
                                        <input
                                            v-model.number="
                                                sectionForm.sort_order
                                            "
                                            type="number"
                                            min="0"
                                            :class="inputClass"
                                        /><select
                                            v-model="sectionForm.status"
                                            :class="inputClass"
                                        >
                                            <option
                                                v-for="option in options.publish_statuses"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                        >
                                            <Plus class="size-4" />{{
                                                editingSection
                                                    ? 'Save section'
                                                    : 'Add section'
                                            }}</button
                                        ><button
                                            v-if="editingSection"
                                            type="button"
                                            class="h-9 rounded-md border px-3 text-sm"
                                            @click="clearSection"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                                <div class="space-y-2">
                                    <div
                                        v-for="section in orderedSections"
                                        :key="section.id"
                                        draggable="true"
                                        class="flex items-center gap-2 rounded-md border bg-background p-3"
                                        @dragstart="
                                            draggedItem = {
                                                type: 'sections',
                                                id: section.id,
                                            }
                                        "
                                        @dragover.prevent
                                        @drop="dropItem('sections', section.id)"
                                    >
                                        <GripVertical
                                            class="size-4 shrink-0 cursor-grab text-muted-foreground"
                                        />
                                        <button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editSection(section)"
                                        >
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ section.title }}</span
                                            ><span
                                                class="text-xs text-muted-foreground capitalize"
                                                >{{ section.status }}</span
                                            >
                                        </button>
                                        <button
                                            type="button"
                                            class="grid size-8 place-items-center rounded hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete section"
                                            @click="
                                                deleteItem(
                                                    section.delete_url,
                                                    section.title,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0 space-y-5">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold">Lesson editor</h3>
                                    <button
                                        v-if="editingLesson"
                                        type="button"
                                        class="text-sm text-muted-foreground hover:text-foreground"
                                        @click="clearLesson"
                                    >
                                        New lesson
                                    </button>
                                </div>
                                <form
                                    class="space-y-4 border-y py-4"
                                    @submit.prevent="saveLesson"
                                >
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label
                                            class="grid gap-2 text-sm font-medium"
                                            >Title<input
                                                v-model="lessonForm.title"
                                                required
                                                :class="inputClass" /></label
                                        ><label
                                            class="grid gap-2 text-sm font-medium"
                                            >Section<select
                                                v-model="
                                                    lessonForm.course_section_id
                                                "
                                                :class="inputClass"
                                            >
                                                <option :value="null">
                                                    No section
                                                </option>
                                                <option
                                                    v-for="section in orderedSections"
                                                    :key="section.id"
                                                    :value="section.id"
                                                >
                                                    {{ section.title }}
                                                </option>
                                            </select></label
                                        >
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <label
                                            class="grid gap-2 text-sm font-medium"
                                            >Order<input
                                                v-model.number="
                                                    lessonForm.order_number
                                                "
                                                type="number"
                                                min="0"
                                                :class="inputClass" /></label
                                        ><label
                                            class="grid gap-2 text-sm font-medium"
                                            >Video type<select
                                                v-model="lessonForm.video_type"
                                                :class="inputClass"
                                            >
                                                <option
                                                    v-for="option in options.video_types"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select></label
                                        ><label
                                            class="grid gap-2 text-sm font-medium"
                                            >Status<select
                                                v-model="lessonForm.status"
                                                :class="inputClass"
                                            >
                                                <option
                                                    v-for="option in options.publish_statuses"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select></label
                                        >
                                    </div>
                                    <div class="grid gap-2 text-sm font-medium">
                                        <span>Lesson content</span
                                        ><RichTextEditor
                                            v-model="lessonForm.content"
                                            placeholder="Write the lesson, add code blocks, lists, and links"
                                        /><span
                                            v-if="lessonForm.errors.content"
                                            class="text-xs text-destructive"
                                            >{{
                                                lessonForm.errors.content
                                            }}</span
                                        >
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label
                                            class="grid gap-2 text-sm font-medium"
                                            >Video URL<input
                                                v-model="lessonForm.video_url"
                                                type="url"
                                                :class="inputClass"
                                        /></label>
                                        <div
                                            class="grid gap-2 text-sm font-medium"
                                        >
                                            Video file<AdminMediaPicker
                                                v-model="
                                                    lessonForm.video_file_id
                                                "
                                                :assets="options.media"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                v-model="lessonForm.is_free"
                                                type="checkbox"
                                            />
                                            Free preview</label
                                        ><label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                v-model="lessonForm.is_paid"
                                                type="checkbox"
                                            />
                                            Paid access</label
                                        ><label
                                            class="grid gap-2 text-sm font-medium"
                                            >Preview words<input
                                                v-model.number="
                                                    lessonForm.preview_word_limit
                                                "
                                                type="number"
                                                min="0"
                                                :class="inputClass"
                                        /></label>
                                    </div>
                                    <details class="rounded-md border p-3">
                                        <summary
                                            class="cursor-pointer text-sm font-medium"
                                        >
                                            Lesson SEO
                                        </summary>
                                        <div
                                            class="mt-3 grid gap-3 md:grid-cols-2"
                                        >
                                            <input
                                                v-model="lessonForm.seo_title"
                                                :class="inputClass"
                                                placeholder="SEO title"
                                            /><textarea
                                                v-model="
                                                    lessonForm.seo_description
                                                "
                                                :class="textareaClass"
                                                placeholder="SEO description"
                                            />
                                        </div>
                                    </details>
                                    <div class="flex gap-2">
                                        <button
                                            class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                                            :disabled="lessonForm.processing"
                                        >
                                            <Save class="size-4" />{{
                                                editingLesson
                                                    ? 'Save lesson'
                                                    : 'Add lesson'
                                            }}</button
                                        ><button
                                            v-if="editingLesson"
                                            type="button"
                                            class="h-10 rounded-md border px-4 text-sm"
                                            @click="clearLesson"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                                <div class="space-y-2">
                                    <div
                                        v-for="lesson in orderedLessons"
                                        :key="lesson.id"
                                        draggable="true"
                                        class="flex items-center gap-2 rounded-md border bg-background p-3"
                                        @dragstart="
                                            draggedItem = {
                                                type: 'lessons',
                                                id: lesson.id,
                                            }
                                        "
                                        @dragover.prevent
                                        @drop="dropItem('lessons', lesson.id)"
                                    >
                                        <GripVertical
                                            class="size-4 shrink-0 cursor-grab text-muted-foreground"
                                        /><button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editLesson(lesson)"
                                        >
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ lesson.title }}</span
                                            ><span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    sectionTitle(
                                                        lesson.course_section_id,
                                                    )
                                                }}
                                                · {{ lesson.video_type }} ·
                                                {{ lesson.status }}</span
                                            ></button
                                        ><button
                                            type="button"
                                            class="grid size-8 place-items-center rounded hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete lesson"
                                            @click="
                                                deleteItem(
                                                    lesson.delete_url,
                                                    lesson.title,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else-if="activeTab === 'resources'"
                        class="space-y-8"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Resources and FAQs
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Protected downloads and course support content.
                            </p>
                        </div>
                        <div class="grid gap-8 xl:grid-cols-2">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold">
                                        Downloadable resources
                                    </h3>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ resources.length }}</span
                                    >
                                </div>
                                <form
                                    class="space-y-3 border-y py-4"
                                    @submit.prevent="saveResource"
                                >
                                    <input
                                        v-model="resourceForm.title"
                                        required
                                        :class="inputClass"
                                        placeholder="Resource title"
                                    />
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <select
                                            v-model="
                                                resourceForm.course_lesson_id
                                            "
                                            :class="inputClass"
                                        >
                                            <option :value="null">
                                                Course level
                                            </option>
                                            <option
                                                v-for="lesson in lessons"
                                                :key="lesson.id"
                                                :value="lesson.id"
                                            >
                                                {{ lesson.title }}
                                            </option></select
                                        ><select
                                            v-model="resourceForm.access_level"
                                            :class="inputClass"
                                        >
                                            <option
                                                v-for="option in options.resource_access"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2 text-sm font-medium">
                                        Media<AdminMediaPicker
                                            v-model="
                                                resourceForm.media_asset_id
                                            "
                                            :assets="options.media"
                                        />
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <input
                                            v-model="resourceForm.file_path"
                                            :class="inputClass"
                                            placeholder="Storage path"
                                        /><input
                                            v-model="resourceForm.external_url"
                                            type="url"
                                            :class="inputClass"
                                            placeholder="External URL"
                                        />
                                    </div>
                                    <textarea
                                        v-model="resourceForm.description"
                                        :class="textareaClass"
                                        placeholder="Description"
                                    />
                                    <div class="flex flex-wrap gap-4 text-sm">
                                        <label class="flex items-center gap-2"
                                            ><input
                                                v-model="
                                                    resourceForm.is_downloadable
                                                "
                                                type="checkbox"
                                            />
                                            Downloadable</label
                                        ><label class="flex items-center gap-2"
                                            ><input
                                                v-model="resourceForm.is_active"
                                                type="checkbox"
                                            />
                                            Active</label
                                        >
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                        >
                                            {{
                                                editingResource
                                                    ? 'Save resource'
                                                    : 'Add resource'
                                            }}</button
                                        ><button
                                            v-if="editingResource"
                                            type="button"
                                            class="h-9 rounded-md border px-3 text-sm"
                                            @click="clearResource"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                                <div class="divide-y border-y">
                                    <div
                                        v-for="item in resources"
                                        :key="item.id"
                                        class="flex items-center gap-3 py-3"
                                    >
                                        <FileText
                                            class="size-4 shrink-0 text-muted-foreground"
                                        /><button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editResource(item)"
                                        >
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ item.title }}</span
                                            ><span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    lessonTitle(
                                                        item.course_lesson_id,
                                                    )
                                                }}
                                                · {{ item.access_level }}</span
                                            ></button
                                        ><button
                                            type="button"
                                            class="grid size-8 place-items-center"
                                            title="Delete resource"
                                            @click="
                                                deleteItem(
                                                    item.delete_url,
                                                    item.title,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold">Course FAQs</h3>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ faqs.length }}</span
                                    >
                                </div>
                                <form
                                    class="space-y-3 border-y py-4"
                                    @submit.prevent="saveFaq"
                                >
                                    <select
                                        v-model="faqForm.course_lesson_id"
                                        :class="inputClass"
                                    >
                                        <option :value="null">
                                            Course level
                                        </option>
                                        <option
                                            v-for="lesson in lessons"
                                            :key="lesson.id"
                                            :value="lesson.id"
                                        >
                                            {{ lesson.title }}
                                        </option></select
                                    ><input
                                        v-model="faqForm.question"
                                        required
                                        :class="inputClass"
                                        placeholder="Question"
                                    /><textarea
                                        v-model="faqForm.answer"
                                        required
                                        :class="textareaClass"
                                        placeholder="Answer"
                                    /><select
                                        v-model="faqForm.status"
                                        :class="inputClass"
                                    >
                                        <option
                                            v-for="option in options.publish_statuses"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <div class="flex gap-2">
                                        <button
                                            class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                        >
                                            {{
                                                editingFaq
                                                    ? 'Save FAQ'
                                                    : 'Add FAQ'
                                            }}</button
                                        ><button
                                            v-if="editingFaq"
                                            type="button"
                                            class="h-9 rounded-md border px-3 text-sm"
                                            @click="clearFaq"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                                <div class="divide-y border-y">
                                    <div
                                        v-for="item in faqs"
                                        :key="item.id"
                                        class="flex items-center gap-3 py-3"
                                    >
                                        <HelpCircle
                                            class="size-4 shrink-0 text-muted-foreground"
                                        /><button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editFaq(item)"
                                        >
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ item.question }}</span
                                            ><span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    lessonTitle(
                                                        item.course_lesson_id,
                                                    )
                                                }}
                                                · {{ item.status }}</span
                                            ></button
                                        ><button
                                            type="button"
                                            class="grid size-8 place-items-center"
                                            title="Delete FAQ"
                                            @click="
                                                deleteItem(
                                                    item.delete_url,
                                                    item.question,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else-if="activeTab === 'assessment'"
                        class="space-y-8"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Quizzes and assignments
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Assessment rules, questions, grading, and
                                submissions.
                            </p>
                        </div>
                        <div class="space-y-5">
                            <h3 class="font-semibold">Quizzes</h3>
                            <form
                                class="grid gap-3 border-y py-4 md:grid-cols-2"
                                @submit.prevent="saveQuiz"
                            >
                                <input
                                    v-model="quizForm.title"
                                    required
                                    :class="inputClass"
                                    placeholder="Quiz title"
                                /><select
                                    v-model="quizForm.course_lesson_id"
                                    :class="inputClass"
                                >
                                    <option :value="null">Course level</option>
                                    <option
                                        v-for="lesson in lessons"
                                        :key="lesson.id"
                                        :value="lesson.id"
                                    >
                                        {{ lesson.title }}
                                    </option></select
                                ><textarea
                                    v-model="quizForm.description"
                                    :class="textareaClass"
                                    placeholder="Description"
                                />
                                <div class="grid grid-cols-3 gap-2">
                                    <input
                                        v-model.number="quizForm.pass_score"
                                        type="number"
                                        min="0"
                                        max="100"
                                        :class="inputClass"
                                        title="Pass score"
                                    /><input
                                        v-model.number="quizForm.max_attempts"
                                        type="number"
                                        min="1"
                                        :class="inputClass"
                                        title="Attempts"
                                    /><input
                                        v-model.number="
                                            quizForm.time_limit_minutes
                                        "
                                        type="number"
                                        min="1"
                                        :class="inputClass"
                                        title="Minutes"
                                    />
                                </div>
                                <div class="flex gap-4 text-sm">
                                    <label class="flex items-center gap-2"
                                        ><input
                                            v-model="quizForm.is_required"
                                            type="checkbox"
                                        />
                                        Required</label
                                    ><label class="flex items-center gap-2"
                                        ><input
                                            v-model="quizForm.is_active"
                                            type="checkbox"
                                        />
                                        Active</label
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                    >
                                        {{
                                            editingQuiz
                                                ? 'Save quiz'
                                                : 'Add quiz'
                                        }}</button
                                    ><button
                                        v-if="editingQuiz"
                                        type="button"
                                        class="h-9 rounded-md border px-3 text-sm"
                                        @click="clearQuiz"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                            <div class="grid gap-2 md:grid-cols-2">
                                <div
                                    v-for="quiz in quizzes"
                                    :key="quiz.id"
                                    class="flex items-center gap-3 rounded-md border bg-background p-3"
                                >
                                    <FileQuestion
                                        class="size-4 shrink-0 text-muted-foreground"
                                    /><button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        @click="editQuiz(quiz)"
                                    >
                                        <span
                                            class="block truncate text-sm font-medium"
                                            >{{ quiz.title }}</span
                                        ><span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                quiz.questions.length
                                            }}
                                            questions · {{ quiz.pass_score }}%
                                            pass</span
                                        ></button
                                    ><button
                                        type="button"
                                        class="grid size-8 place-items-center"
                                        title="Delete quiz"
                                        @click="
                                            deleteItem(
                                                quiz.delete_url,
                                                quiz.title,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-5">
                            <h3 class="font-semibold">Quiz questions</h3>
                            <form
                                class="grid gap-3 border-y py-4 md:grid-cols-2"
                                @submit.prevent="saveQuestion"
                            >
                                <select
                                    v-model="questionForm.quiz_id"
                                    required
                                    :class="inputClass"
                                >
                                    <option :value="null" disabled>
                                        Select quiz
                                    </option>
                                    <option
                                        v-for="quiz in quizzes"
                                        :key="quiz.id"
                                        :value="quiz.id"
                                    >
                                        {{ quiz.title }}
                                    </option></select
                                ><select
                                    v-model="questionForm.type"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="option in options.question_types"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option></select
                                ><textarea
                                    v-model="questionForm.question"
                                    required
                                    :class="textareaClass"
                                    placeholder="Question"
                                /><textarea
                                    v-model="questionForm.explanation"
                                    :class="textareaClass"
                                    placeholder="Answer explanation"
                                /><label class="grid gap-2 text-sm font-medium"
                                    >Options JSON<textarea
                                        v-model="questionForm.options_json"
                                        :class="`${textareaClass} font-mono`"
                                    /></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Correct answer JSON<textarea
                                        v-model="
                                            questionForm.correct_answer_json
                                        "
                                        required
                                        :class="`${textareaClass} font-mono`"
                                    /></label
                                ><input
                                    v-model.number="questionForm.points"
                                    type="number"
                                    min="1"
                                    :class="inputClass"
                                    placeholder="Points"
                                />
                                <div class="flex gap-2">
                                    <button
                                        class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                    >
                                        {{
                                            editingQuestion
                                                ? 'Save question'
                                                : 'Add question'
                                        }}</button
                                    ><button
                                        v-if="editingQuestion"
                                        type="button"
                                        class="h-9 rounded-md border px-3 text-sm"
                                        @click="clearQuestion"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                            <div class="divide-y border-y">
                                <template v-for="quiz in quizzes" :key="quiz.id"
                                    ><div
                                        v-for="question in quiz.questions"
                                        :key="question.id"
                                        class="flex items-center gap-3 py-3"
                                    >
                                        <span
                                            class="grid size-7 shrink-0 place-items-center rounded border text-xs font-semibold"
                                            >{{ question.points }}</span
                                        ><button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editQuestion(question)"
                                        >
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ question.question }}</span
                                            ><span
                                                class="text-xs text-muted-foreground"
                                                >{{ quiz.title }} ·
                                                {{ question.type }}</span
                                            ></button
                                        ><button
                                            type="button"
                                            class="grid size-8 place-items-center"
                                            title="Delete question"
                                            @click="
                                                deleteItem(
                                                    question.delete_url,
                                                    'question',
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button></div
                                ></template>
                            </div>
                        </div>
                        <div class="space-y-5">
                            <h3 class="font-semibold">Assignments</h3>
                            <form
                                class="space-y-3 border-y py-4"
                                @submit.prevent="saveAssignment"
                            >
                                <div class="grid gap-3 md:grid-cols-2">
                                    <input
                                        v-model="assignmentForm.title"
                                        required
                                        :class="inputClass"
                                        placeholder="Assignment title"
                                    /><select
                                        v-model="
                                            assignmentForm.course_lesson_id
                                        "
                                        :class="inputClass"
                                    >
                                        <option :value="null">
                                            Course level
                                        </option>
                                        <option
                                            v-for="lesson in lessons"
                                            :key="lesson.id"
                                            :value="lesson.id"
                                        >
                                            {{ lesson.title }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-2 text-sm font-medium">
                                    <span>Instructions</span
                                    ><RichTextEditor
                                        v-model="assignmentForm.instructions"
                                        placeholder="Write assignment instructions and submission criteria"
                                    />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <input
                                        v-model.number="
                                            assignmentForm.pass_score
                                        "
                                        type="number"
                                        min="0"
                                        :class="inputClass"
                                        title="Pass score"
                                    /><input
                                        v-model.number="
                                            assignmentForm.max_points
                                        "
                                        type="number"
                                        min="1"
                                        :class="inputClass"
                                        title="Maximum points"
                                    /><input
                                        v-model.number="
                                            assignmentForm.due_days_after_enrollment
                                        "
                                        type="number"
                                        min="0"
                                        :class="inputClass"
                                        title="Due after days"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <label class="flex items-center gap-2"
                                        ><input
                                            v-model="
                                                assignmentForm.allow_file_uploads
                                            "
                                            type="checkbox"
                                        />
                                        File uploads</label
                                    ><label class="flex items-center gap-2"
                                        ><input
                                            v-model="assignmentForm.is_required"
                                            type="checkbox"
                                        />
                                        Required</label
                                    ><label class="flex items-center gap-2"
                                        ><input
                                            v-model="assignmentForm.is_active"
                                            type="checkbox"
                                        />
                                        Active</label
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                    >
                                        {{
                                            editingAssignment
                                                ? 'Save assignment'
                                                : 'Add assignment'
                                        }}</button
                                    ><button
                                        v-if="editingAssignment"
                                        type="button"
                                        class="h-9 rounded-md border px-3 text-sm"
                                        @click="clearAssignment"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                            <div class="grid gap-2 md:grid-cols-2">
                                <div
                                    v-for="item in assignments"
                                    :key="item.id"
                                    class="flex items-center gap-3 rounded-md border bg-background p-3"
                                >
                                    <ClipboardCheck
                                        class="size-4 shrink-0 text-muted-foreground"
                                    /><button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        @click="editAssignment(item)"
                                    >
                                        <span
                                            class="block truncate text-sm font-medium"
                                            >{{ item.title }}</span
                                        ><span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                lessonTitle(
                                                    item.course_lesson_id,
                                                )
                                            }}
                                            · {{ item.max_points }} points</span
                                        ></button
                                    ><button
                                        type="button"
                                        class="grid size-8 place-items-center"
                                        title="Delete assignment"
                                        @click="
                                            deleteItem(
                                                item.delete_url,
                                                item.title,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else-if="activeTab === 'pricing'"
                        class="space-y-8"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Pricing and access
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Free access or Paddle-ready product and price
                                plans.
                            </p>
                        </div>
                        <form
                            class="space-y-5"
                            @submit.prevent="
                                productForm.patch(urls.product, {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <label
                                class="flex w-fit items-center gap-3 rounded-md border px-4 py-3 text-sm font-semibold"
                                ><input
                                    v-model="productForm.is_free"
                                    type="checkbox"
                                />
                                Free course</label
                            >
                            <div
                                v-if="!productForm.is_free"
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <label class="grid gap-2 text-sm font-medium"
                                    >Display price<input
                                        v-model.number="productForm.price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="inputClass" /></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Product name<input
                                        v-model="productForm.name"
                                        required
                                        :class="inputClass" /></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Product type<select
                                        v-model="productForm.type"
                                        :class="inputClass"
                                    >
                                        <option
                                            v-for="option in options.product_types"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Status<select
                                        v-model="productForm.status"
                                        :class="inputClass"
                                    >
                                        <option
                                            v-for="option in options.product_statuses"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Paddle product ID<input
                                        v-model="productForm.paddle_product_id"
                                        :class="inputClass" /></label
                                ><label class="grid gap-2 text-sm font-medium"
                                    >Tax category<input
                                        v-model="productForm.tax_category"
                                        :class="inputClass" /></label
                                ><label
                                    class="grid gap-2 text-sm font-medium md:col-span-2"
                                    >Description<textarea
                                        v-model="productForm.description"
                                        :class="textareaClass"
                                    />
                                </label>
                            </div>
                            <button
                                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            >
                                <Save class="size-4" /> Save access
                            </button>
                        </form>
                        <div v-if="!productForm.is_free" class="space-y-5">
                            <h3 class="font-semibold">Price plans</h3>
                            <form
                                class="grid gap-3 border-y py-4 md:grid-cols-2"
                                @submit.prevent="savePrice"
                            >
                                <input
                                    v-model="priceForm.name"
                                    required
                                    :class="inputClass"
                                    placeholder="Plan name"
                                /><input
                                    v-model="priceForm.paddle_price_id"
                                    :class="inputClass"
                                    placeholder="Paddle price ID"
                                /><select
                                    v-model="priceForm.billing_interval"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="option in options.billing_intervals"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <div class="grid grid-cols-[6rem_1fr] gap-2">
                                    <input
                                        v-model="priceForm.currency"
                                        maxlength="3"
                                        required
                                        :class="inputClass"
                                    /><input
                                        v-model.number="priceForm.amount"
                                        type="number"
                                        min="0"
                                        required
                                        :class="inputClass"
                                        title="Amount in cents"
                                    />
                                </div>
                                <div class="flex gap-4 text-sm">
                                    <label class="flex items-center gap-2"
                                        ><input
                                            v-model="priceForm.is_recurring"
                                            type="checkbox"
                                        />
                                        Recurring</label
                                    ><label class="flex items-center gap-2"
                                        ><input
                                            v-model="priceForm.is_active"
                                            type="checkbox"
                                        />
                                        Active</label
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                    >
                                        {{
                                            editingPrice
                                                ? 'Save price'
                                                : 'Add price'
                                        }}</button
                                    ><button
                                        v-if="editingPrice"
                                        type="button"
                                        class="h-9 rounded-md border px-3 text-sm"
                                        @click="clearPrice"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                            <div class="divide-y border-y">
                                <div
                                    v-for="item in pricing.prices"
                                    :key="item.id"
                                    class="flex items-center gap-3 py-3"
                                >
                                    <BadgeDollarSign
                                        class="size-4 shrink-0 text-muted-foreground"
                                    /><button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        @click="editPrice(item)"
                                    >
                                        <span
                                            class="block text-sm font-medium"
                                            >{{ item.name }}</span
                                        ><span
                                            class="text-xs text-muted-foreground"
                                            >{{ item.currency }}
                                            {{ (item.amount / 100).toFixed(2) }}
                                            · {{ item.billing_interval }}</span
                                        ></button
                                    ><button
                                        type="button"
                                        class="grid size-8 place-items-center"
                                        title="Delete price"
                                        @click="
                                            deleteItem(
                                                item.delete_url,
                                                item.name,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-else-if="activeTab === 'seo'" class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold">
                                Search and social metadata
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Course discovery, canonical identity, and
                                structured data.
                            </p>
                        </div>
                        <form
                            class="grid gap-4 md:grid-cols-2"
                            @submit.prevent="saveCourse"
                        >
                            <label class="grid gap-2 text-sm font-medium"
                                >SEO title<input
                                    v-model="courseForm.seo_title"
                                    :class="inputClass" /></label
                            ><label class="grid gap-2 text-sm font-medium"
                                >Social image URL<input
                                    v-model="courseForm.seo_image"
                                    type="url"
                                    :class="inputClass" /></label
                            ><label
                                class="grid gap-2 text-sm font-medium md:col-span-2"
                                >SEO description<textarea
                                    v-model="courseForm.seo_description"
                                    :class="textareaClass"
                                /></label
                            ><label
                                class="grid gap-2 text-sm font-medium md:col-span-2"
                                >Canonical URL<input
                                    v-model="courseForm.canonical_url"
                                    type="url"
                                    :class="inputClass" /></label
                            ><label
                                class="grid gap-2 text-sm font-medium md:col-span-2"
                                >Schema JSON<textarea
                                    v-model="courseForm.schema"
                                    :class="`${textareaClass} min-h-72 font-mono`"
                                    spellcheck="false"
                                /><span
                                    v-if="courseForm.errors.schema"
                                    class="text-xs text-destructive"
                                    >{{ courseForm.errors.schema }}</span
                                ></label
                            ><button
                                class="inline-flex h-10 w-fit items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            >
                                <Save class="size-4" /> Save SEO
                            </button>
                        </form>
                    </section>

                    <section
                        v-else-if="activeTab === 'ownership'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Ownership confirmation
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Evidence and declaration required for course
                                approval.
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-md border px-4 py-3"
                            :class="
                                course.ownership_complete
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-950'
                                    : 'border-amber-300 bg-amber-50 text-amber-950'
                            "
                        >
                            <CheckCircle2
                                v-if="course.ownership_complete"
                                class="size-5"
                            /><CircleAlert v-else class="size-5" /><span
                                class="text-sm font-semibold"
                                >{{
                                    course.ownership_complete
                                        ? 'Ownership evidence complete'
                                        : 'Ownership evidence incomplete'
                                }}</span
                            >
                        </div>
                        <form
                            class="space-y-5"
                            @submit.prevent="
                                ownershipForm.patch(urls.ownership, {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2 text-sm font-medium">
                                    Ownership video<AdminMediaPicker
                                        v-model="
                                            ownershipForm.ownership_video_media_id
                                        "
                                        :assets="options.media"
                                    />
                                </div>
                                <label class="grid gap-2 text-sm font-medium"
                                    >External video URL<input
                                        v-model="
                                            ownershipForm.ownership_video_url
                                        "
                                        type="url"
                                        :class="inputClass"
                                    /><span
                                        v-if="
                                            ownershipForm.errors
                                                .ownership_video_url
                                        "
                                        class="text-xs text-destructive"
                                        >{{
                                            ownershipForm.errors
                                                .ownership_video_url
                                        }}</span
                                    ></label
                                >
                            </div>
                            <label class="grid gap-2 text-sm font-medium"
                                >Ownership statement<textarea
                                    v-model="ownershipForm.ownership_statement"
                                    required
                                    :class="`${textareaClass} min-h-36`"
                                /></label
                            ><label
                                class="flex items-start gap-3 rounded-md border p-4 text-sm font-medium"
                                ><input
                                    v-model="ownershipForm.confirmed"
                                    type="checkbox"
                                    class="mt-0.5"
                                    required
                                />
                                I confirm the creator owns or holds sufficient
                                rights to publish this course and its included
                                materials.</label
                            ><button
                                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            >
                                <ShieldCheck class="size-4" /> Save confirmation
                            </button>
                        </form>
                    </section>

                    <section v-else class="space-y-8">
                        <div>
                            <h2 class="text-xl font-semibold">
                                Publishing and approval
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Readiness, decisions, reviewer notes, and
                                immutable history.
                            </p>
                        </div>
                        <div
                            v-if="readiness_issues.length"
                            class="rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950"
                        >
                            <div class="flex items-center gap-2 font-semibold">
                                <CircleAlert class="size-4" /> Approval blockers
                            </div>
                            <ul class="mt-3 space-y-1 text-sm">
                                <li
                                    v-for="issue in readiness_issues"
                                    :key="issue"
                                >
                                    {{ issue }}
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-3 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-emerald-950"
                        >
                            <CheckCircle2 class="size-5" /><span
                                class="text-sm font-semibold"
                                >Course is ready for review or
                                publication.</span
                            >
                        </div>
                        <form
                            class="grid gap-4 md:grid-cols-2"
                            @submit.prevent="
                                publishingForm.patch(urls.publishing, {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <label class="grid gap-2 text-sm font-medium"
                                >Status<select
                                    v-model="publishingForm.status"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="option in options.publish_statuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option></select
                                ><span
                                    v-if="publishingForm.errors.status"
                                    class="text-xs text-destructive"
                                    >{{ publishingForm.errors.status }}</span
                                ></label
                            >
                            <div />
                            <label class="grid gap-2 text-sm font-medium"
                                >Admin notes<textarea
                                    v-model="publishingForm.admin_notes"
                                    :class="textareaClass"
                                /></label
                            ><label class="grid gap-2 text-sm font-medium"
                                >Rejection or change reason<textarea
                                    v-model="publishingForm.rejection_reason"
                                    :class="textareaClass"
                                /></label
                            ><button
                                class="inline-flex h-10 w-fit items-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            >
                                <ChevronRight class="size-4" /> Record decision
                            </button>
                        </form>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold">Approval history</h3>
                                <span class="text-xs text-muted-foreground"
                                    >{{
                                        approval_history.length
                                    }}
                                    decisions</span
                                >
                            </div>
                            <div class="divide-y border-y">
                                <div
                                    v-if="!approval_history.length"
                                    class="py-6 text-sm text-muted-foreground"
                                >
                                    No decisions recorded.
                                </div>
                                <div
                                    v-for="item in approval_history"
                                    :key="item.id"
                                    class="grid gap-2 py-4 md:grid-cols-[12rem_1fr_auto]"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold capitalize"
                                        >
                                            {{
                                                item.decision.replaceAll(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.actor || 'System' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm">
                                            {{ item.from_status || 'new' }} →
                                            {{ item.to_status || 'unchanged' }}
                                        </p>
                                        <p
                                            v-if="item.note"
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ item.note }}
                                        </p>
                                    </div>
                                    <time
                                        class="text-xs text-muted-foreground"
                                        >{{ formatDate(item.created_at) }}</time
                                    >
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
