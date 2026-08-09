<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle2,
    FileText,
    HelpCircle,
    Layers3,
    Video,
} from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    id: number;
    name: string;
    course_category_id?: number;
    url?: string | null;
};

type SelectOption = {
    label: string;
    value: string;
};

type CourseItem = {
    id: number;
    title: string;
    category_id: number | null;
    subcategory_id: number | null;
    short_description: string | null;
    description: string | null;
    intro_video_url: string | null;
    thumbnail_media_id: number | null;
    level: string | null;
    language: string | null;
    price: string | null;
    is_free: boolean;
    seo_title: string | null;
    seo_description: string | null;
    ownership_video_media_id: number | null;
    ownership_video_url: string | null;
    ownership_statement: string | null;
    ownership_confirmed_at: string | null;
    ownership_complete: boolean;
    status: string | null;
    rejection_reason: string | null;
    admin_notes: string | null;
    active_revision?: {
        id: number;
        status: string | null;
        title: string;
        review_note: string | null;
        submitted_at: string | null;
        reviewed_at: string | null;
    } | null;
};

type SectionItem = {
    id: number;
    title: string;
    description: string | null;
    sort_order: number | null;
    status: string | null;
    update_url: string;
    delete_url: string;
};

type LessonItem = {
    id: number;
    course_section_id: number | null;
    section_title: string | null;
    title: string;
    order_number: number | null;
    content: string | null;
    video_type: string | null;
    video_url: string | null;
    video_file_id: number | null;
    is_free: boolean;
    is_paid: boolean;
    preview_word_limit: number | null;
    status: string | null;
    update_url: string;
    delete_url: string;
};

type ResourceItem = {
    id: number;
    course_lesson_id: number | null;
    lesson_title: string | null;
    media_asset_id: number | null;
    title: string;
    description: string | null;
    type: string | null;
    access_level: string | null;
    file_path: string | null;
    external_url: string | null;
    is_downloadable: boolean;
    is_active: boolean;
    sort_order: number | null;
    update_url: string;
    delete_url: string;
};

type FaqItem = {
    id: number;
    course_lesson_id: number | null;
    lesson_title: string | null;
    question: string;
    answer: string;
    sort_order: number | null;
    status: string | null;
    update_url: string;
    delete_url: string;
};

type BuilderPayload = {
    readiness_issues: string[];
    sections: SectionItem[];
    lessons: LessonItem[];
    resources: ResourceItem[];
    faqs: FaqItem[];
};

const props = defineProps<{
    title: string;
    item: CourseItem;
    builder: BuilderPayload;
    categories: Option[];
    subcategories: Option[];
    media_assets: Option[];
    video_types: SelectOption[];
    resource_access_options: SelectOption[];
    update_url: string;
    submit_url: string | null;
    delete_request_url: string | null;
    ownership_update_url: string;
    section_store_url: string;
    lesson_store_url: string;
    resource_store_url: string;
    faq_store_url: string;
    cancel_url: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator courses',
                href: '/creator/courses',
            },
        ],
    },
});

const canSubmit =
    props.submit_url !== null && props.builder.readiness_issues.length === 0;
const isRevisionMode = props.item.status === 'published';

function mediaLabel(id: number | null) {
    return (
        props.media_assets.find((asset) => asset.id === id)?.name ||
        'No media selected'
    );
}

function deleteItem(url: string) {
    router.delete(url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary">
                    Course workflow
                </p>
                <h1 class="text-2xl font-semibold tracking-normal">
                    {{ item.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        isRevisionMode
                            ? 'Published content edits are sent for review.'
                            : item.status || 'draft'
                    }}
                </p>
            </div>
            <Link
                :href="cancel_url"
                class="rounded-md border px-4 py-2 text-sm font-semibold"
            >
                Back
            </Link>
        </div>

        <section
            v-if="item.rejection_reason || item.admin_notes"
            class="rounded-lg border border-rose-200 bg-rose-50 p-5 text-rose-950"
        >
            <h2 class="font-semibold">Reviewer notes</h2>
            <p class="mt-1 text-sm">
                {{ item.rejection_reason || item.admin_notes }}
            </p>
        </section>

        <section
            v-if="item.active_revision"
            class="rounded-lg border border-teal-200 bg-teal-50 p-5 text-teal-950"
        >
            <h2 class="font-semibold">Active revision</h2>
            <p class="mt-1 text-sm">
                {{ item.active_revision.title }} /
                {{ item.active_revision.status || 'submitted' }}
            </p>
            <p v-if="item.active_revision.review_note" class="mt-2 text-sm">
                {{ item.active_revision.review_note }}
            </p>
        </section>

        <section class="grid gap-3 md:grid-cols-5">
            <div class="rounded-lg border bg-card p-4">
                <Layers3 class="size-5 text-primary" />
                <p class="mt-3 text-2xl font-semibold">
                    {{ builder.sections.length }}
                </p>
                <p class="text-sm text-muted-foreground">Sections</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <BookOpen class="size-5 text-primary" />
                <p class="mt-3 text-2xl font-semibold">
                    {{ builder.lessons.length }}
                </p>
                <p class="text-sm text-muted-foreground">Lessons</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <FileText class="size-5 text-primary" />
                <p class="mt-3 text-2xl font-semibold">
                    {{ builder.resources.length }}
                </p>
                <p class="text-sm text-muted-foreground">Resources</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <HelpCircle class="size-5 text-primary" />
                <p class="mt-3 text-2xl font-semibold">
                    {{ builder.faqs.length }}
                </p>
                <p class="text-sm text-muted-foreground">FAQs</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <CheckCircle2
                    class="size-5"
                    :class="
                        item.ownership_complete
                            ? 'text-emerald-600'
                            : 'text-amber-600'
                    "
                />
                <p class="mt-3 text-sm font-semibold">
                    {{
                        item.ownership_complete
                            ? 'Ownership complete'
                            : 'Ownership missing'
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ mediaLabel(item.ownership_video_media_id) }}
                </p>
            </div>
        </section>

        <section
            v-if="builder.readiness_issues.length > 0"
            class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-950"
        >
            <h2 class="font-semibold">Before approval</h2>
            <ul class="mt-2 grid gap-1 text-sm">
                <li v-for="issue in builder.readiness_issues" :key="issue">
                    {{ issue }}
                </li>
            </ul>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <Form
                :action="update_url"
                method="patch"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-normal">
                        Course info
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Core catalog, pricing, media, and SEO fields.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        :default-value="item.title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="course_category_id">Category</Label>
                        <select
                            id="course_category_id"
                            name="course_category_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">Uncategorized</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                                :selected="category.id === item.category_id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="errors.course_category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="course_subcategory_id">Subcategory</Label>
                        <select
                            id="course_subcategory_id"
                            name="course_subcategory_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No subcategory</option>
                            <option
                                v-for="subcategory in subcategories"
                                :key="subcategory.id"
                                :value="subcategory.id"
                                :selected="
                                    subcategory.id === item.subcategory_id
                                "
                            >
                                {{ subcategory.name }}
                            </option>
                        </select>
                        <InputError :message="errors.course_subcategory_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="thumbnail_media_id">Thumbnail</Label>
                        <select
                            id="thumbnail_media_id"
                            name="thumbnail_media_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No thumbnail</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                                :selected="asset.id === item.thumbnail_media_id"
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                        <InputError :message="errors.thumbnail_media_id" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="short_description">Short description</Label>
                    <textarea
                        id="short_description"
                        name="short_description"
                        class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="item.short_description || ''"
                    />
                    <InputError :message="errors.short_description" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        required
                        class="min-h-44 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="item.description || ''"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="level">Level</Label>
                        <Input
                            id="level"
                            name="level"
                            :default-value="item.level || 'intermediate'"
                        />
                        <InputError :message="errors.level" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="language">Language</Label>
                        <Input
                            id="language"
                            name="language"
                            :default-value="item.language || 'en'"
                        />
                        <InputError :message="errors.language" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="price">Price</Label>
                        <Input
                            id="price"
                            name="price"
                            type="number"
                            min="0"
                            step="0.01"
                            :default-value="item.price || '0'"
                        />
                        <InputError :message="errors.price" />
                    </div>
                    <label class="mt-8 flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_free" value="0" />
                        <input
                            type="checkbox"
                            name="is_free"
                            value="1"
                            :checked="item.is_free"
                            class="h-4 w-4 rounded border-input"
                        />
                        Free course
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="intro_video_url">Intro video URL</Label>
                    <Input
                        id="intro_video_url"
                        name="intro_video_url"
                        type="url"
                        :default-value="item.intro_video_url || ''"
                    />
                    <InputError :message="errors.intro_video_url" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="seo_title">SEO title</Label>
                        <Input
                            id="seo_title"
                            name="seo_title"
                            :default-value="item.seo_title || ''"
                        />
                        <InputError :message="errors.seo_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="seo_description">SEO description</Label>
                        <Input
                            id="seo_description"
                            name="seo_description"
                            :default-value="item.seo_description || ''"
                        />
                        <InputError :message="errors.seo_description" />
                    </div>
                </div>

                <div
                    v-if="isRevisionMode"
                    class="rounded-md border border-amber-200 bg-amber-50 p-4"
                >
                    <label
                        class="flex items-start gap-3 text-sm font-semibold text-amber-950"
                    >
                        <input
                            type="checkbox"
                            name="copyright_declaration_accepted"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-input"
                        />
                        I confirm these course changes are original or properly
                        licensed, and I have rights to submit them for review.
                    </label>
                    <InputError
                        class="mt-2"
                        :message="errors.copyright_declaration_accepted"
                    />
                </div>

                <Button type="submit" class="w-fit" :disabled="processing">
                    {{
                        isRevisionMode
                            ? 'Submit course info revision'
                            : 'Save course info'
                    }}
                </Button>
            </Form>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <Form
                :action="ownership_update_url"
                method="patch"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-normal">
                        Ownership proof
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Creator confirmation needed before admin approval.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ownership_video_media_id">
                            Ownership video file
                        </Label>
                        <select
                            id="ownership_video_media_id"
                            name="ownership_video_media_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No media file</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                                :selected="
                                    asset.id === item.ownership_video_media_id
                                "
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                        <InputError
                            :message="errors.ownership_video_media_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ownership_video_url"
                            >Ownership video URL</Label
                        >
                        <Input
                            id="ownership_video_url"
                            name="ownership_video_url"
                            type="url"
                            :default-value="item.ownership_video_url || ''"
                        />
                        <InputError :message="errors.ownership_video_url" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="ownership_statement">Ownership statement</Label>
                    <textarea
                        id="ownership_statement"
                        name="ownership_statement"
                        required
                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="item.ownership_statement || ''"
                    />
                    <InputError :message="errors.ownership_statement" />
                </div>

                <div
                    v-if="isRevisionMode"
                    class="rounded-md border border-amber-200 bg-amber-50 p-4"
                >
                    <label
                        class="flex items-start gap-3 text-sm font-semibold text-amber-950"
                    >
                        <input
                            type="checkbox"
                            name="copyright_declaration_accepted"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-input"
                        />
                        I confirm this ownership proof is accurate and I have
                        rights to publish this course on Scratch Learning.
                    </label>
                    <InputError
                        class="mt-2"
                        :message="errors.copyright_declaration_accepted"
                    />
                </div>

                <Button type="submit" class="w-fit" :disabled="processing">
                    {{
                        isRevisionMode
                            ? 'Submit ownership revision'
                            : 'Save ownership proof'
                    }}
                </Button>
            </Form>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <div class="mb-5 flex items-center gap-2">
                <Layers3 class="size-5 text-primary" />
                <h2 class="text-lg font-semibold tracking-normal">Sections</h2>
            </div>

            <Form
                :action="section_store_url"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-4 border-b pb-5"
            >
                <div class="grid gap-4 md:grid-cols-[1fr_1fr_8rem_auto]">
                    <div class="grid gap-2">
                        <Label for="section_title">Title</Label>
                        <Input id="section_title" name="title" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="section_description">Description</Label>
                        <Input id="section_description" name="description" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="section_sort_order">Order</Label>
                        <Input
                            id="section_sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            default-value="0"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>
                    <Button type="submit" class="mt-7" :disabled="processing">
                        {{ isRevisionMode ? 'Submit addition' : 'Add' }}
                    </Button>
                </div>
            </Form>

            <div class="mt-5 grid gap-3">
                <Form
                    v-for="section in builder.sections"
                    :key="section.id"
                    :action="section.update_url"
                    method="patch"
                    v-slot="{ processing }"
                    class="grid gap-3 rounded-md border p-4"
                >
                    <div
                        class="grid gap-3 md:grid-cols-[1fr_1fr_8rem_auto_auto]"
                    >
                        <Input
                            name="title"
                            :default-value="section.title"
                            required
                        />
                        <Input
                            name="description"
                            :default-value="section.description || ''"
                        />
                        <Input
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="section.sort_order || 0"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteItem(section.delete_url)"
                        >
                            {{ isRevisionMode ? 'Submit removal' : 'Delete' }}
                        </Button>
                    </div>
                </Form>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <div class="mb-5 flex items-center gap-2">
                <Video class="size-5 text-primary" />
                <h2 class="text-lg font-semibold tracking-normal">Lessons</h2>
            </div>

            <Form
                :action="lesson_store_url"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-4 border-b pb-5"
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="lesson_title">Title</Label>
                        <Input id="lesson_title" name="title" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson_section">Section</Label>
                        <select
                            id="lesson_section"
                            name="course_section_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No section</option>
                            <option
                                v-for="section in builder.sections"
                                :key="section.id"
                                :value="section.id"
                            >
                                {{ section.title }}
                            </option>
                        </select>
                        <InputError :message="errors.course_section_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson_order">Order</Label>
                        <Input
                            id="lesson_order"
                            name="order_number"
                            type="number"
                            min="0"
                            default-value="1"
                        />
                        <InputError :message="errors.order_number" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="lesson_video_type">Video type</Label>
                        <select
                            id="lesson_video_type"
                            name="video_type"
                            required
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option
                                v-for="option in video_types"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === 'url'"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.video_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson_video_url">Video URL</Label>
                        <Input
                            id="lesson_video_url"
                            name="video_url"
                            type="url"
                        />
                        <InputError :message="errors.video_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson_video_file_id">Video file</Label>
                        <select
                            id="lesson_video_file_id"
                            name="video_file_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No video file</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                        <InputError :message="errors.video_file_id" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="lesson_content">Content</Label>
                    <textarea
                        id="lesson_content"
                        name="content"
                        class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.content" />
                </div>

                <div class="flex flex-wrap items-center gap-5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_free" value="0" />
                        <input
                            type="checkbox"
                            name="is_free"
                            value="1"
                            class="h-4 w-4 rounded border-input"
                        />
                        Free preview
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_paid" value="0" />
                        <input
                            type="checkbox"
                            name="is_paid"
                            value="1"
                            checked
                            class="h-4 w-4 rounded border-input"
                        />
                        Paid lesson
                    </label>
                    <Input
                        name="preview_word_limit"
                        type="number"
                        min="0"
                        default-value="120"
                        class="w-40"
                    />
                    <Button type="submit" :disabled="processing">
                        {{
                            isRevisionMode
                                ? 'Submit lesson addition'
                                : 'Add lesson'
                        }}
                    </Button>
                </div>
            </Form>

            <div class="mt-5 grid gap-3">
                <Form
                    v-for="lesson in builder.lessons"
                    :key="lesson.id"
                    :action="lesson.update_url"
                    method="patch"
                    v-slot="{ processing }"
                    class="grid gap-3 rounded-md border p-4"
                >
                    <div
                        class="grid gap-3 md:grid-cols-[1fr_12rem_7rem_auto_auto]"
                    >
                        <Input
                            name="title"
                            :default-value="lesson.title"
                            required
                        />
                        <select
                            name="course_section_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No section</option>
                            <option
                                v-for="section in builder.sections"
                                :key="section.id"
                                :value="section.id"
                                :selected="
                                    section.id === lesson.course_section_id
                                "
                            >
                                {{ section.title }}
                            </option>
                        </select>
                        <Input
                            name="order_number"
                            type="number"
                            min="0"
                            :default-value="lesson.order_number || 0"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteItem(lesson.delete_url)"
                        >
                            {{ isRevisionMode ? 'Submit removal' : 'Delete' }}
                        </Button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[10rem_1fr_12rem]">
                        <select
                            name="video_type"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option
                                v-for="option in video_types"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === lesson.video_type"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <Input
                            name="video_url"
                            type="url"
                            :default-value="lesson.video_url || ''"
                        />
                        <select
                            name="video_file_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No video file</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                                :selected="asset.id === lesson.video_file_id"
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                    </div>

                    <textarea
                        name="content"
                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="lesson.content || ''"
                    />

                    <div class="flex flex-wrap items-center gap-5">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_free" value="0" />
                            <input
                                type="checkbox"
                                name="is_free"
                                value="1"
                                :checked="lesson.is_free"
                                class="h-4 w-4 rounded border-input"
                            />
                            Free preview
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_paid" value="0" />
                            <input
                                type="checkbox"
                                name="is_paid"
                                value="1"
                                :checked="lesson.is_paid"
                                class="h-4 w-4 rounded border-input"
                            />
                            Paid lesson
                        </label>
                        <Input
                            name="preview_word_limit"
                            type="number"
                            min="0"
                            :default-value="lesson.preview_word_limit || 0"
                            class="w-40"
                        />
                    </div>
                </Form>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <div class="mb-5 flex items-center gap-2">
                <FileText class="size-5 text-primary" />
                <h2 class="text-lg font-semibold tracking-normal">Resources</h2>
            </div>

            <Form
                :action="resource_store_url"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-4 border-b pb-5"
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="resource_title">Title</Label>
                        <Input id="resource_title" name="title" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="resource_lesson">Lesson</Label>
                        <select
                            id="resource_lesson"
                            name="course_lesson_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">Course level</option>
                            <option
                                v-for="lesson in builder.lessons"
                                :key="lesson.id"
                                :value="lesson.id"
                            >
                                {{ lesson.title }}
                            </option>
                        </select>
                        <InputError :message="errors.course_lesson_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="resource_media_asset_id">Media file</Label>
                        <select
                            id="resource_media_asset_id"
                            name="media_asset_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No media file</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                        <InputError :message="errors.media_asset_id" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="resource_type">Type</Label>
                        <Input
                            id="resource_type"
                            name="type"
                            default-value="download"
                        />
                        <InputError :message="errors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="resource_access_level">Access</Label>
                        <select
                            id="resource_access_level"
                            name="access_level"
                            required
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option
                                v-for="option in resource_access_options"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === 'enrolled'"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.access_level" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="resource_external_url">External URL</Label>
                        <Input
                            id="resource_external_url"
                            name="external_url"
                            type="url"
                        />
                        <InputError :message="errors.external_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="resource_sort_order">Order</Label>
                        <Input
                            id="resource_sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            default-value="0"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="resource_description">Description</Label>
                    <Input id="resource_description" name="description" />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex flex-wrap items-center gap-5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_downloadable" value="0" />
                        <input
                            type="checkbox"
                            name="is_downloadable"
                            value="1"
                            checked
                            class="h-4 w-4 rounded border-input"
                        />
                        Downloadable
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            checked
                            class="h-4 w-4 rounded border-input"
                        />
                        Active
                    </label>
                    <Button type="submit" :disabled="processing">
                        {{
                            isRevisionMode
                                ? 'Submit resource addition'
                                : 'Add resource'
                        }}
                    </Button>
                </div>
            </Form>

            <div class="mt-5 grid gap-3">
                <Form
                    v-for="resource in builder.resources"
                    :key="resource.id"
                    :action="resource.update_url"
                    method="patch"
                    v-slot="{ processing }"
                    class="grid gap-3 rounded-md border p-4"
                >
                    <div
                        class="grid gap-3 md:grid-cols-[1fr_12rem_12rem_auto_auto]"
                    >
                        <Input
                            name="title"
                            :default-value="resource.title"
                            required
                        />
                        <select
                            name="course_lesson_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">Course level</option>
                            <option
                                v-for="lesson in builder.lessons"
                                :key="lesson.id"
                                :value="lesson.id"
                                :selected="
                                    lesson.id === resource.course_lesson_id
                                "
                            >
                                {{ lesson.title }}
                            </option>
                        </select>
                        <select
                            name="media_asset_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">No media file</option>
                            <option
                                v-for="asset in media_assets"
                                :key="asset.id"
                                :value="asset.id"
                                :selected="asset.id === resource.media_asset_id"
                            >
                                {{ asset.name }}
                            </option>
                        </select>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteItem(resource.delete_url)"
                        >
                            {{ isRevisionMode ? 'Submit removal' : 'Delete' }}
                        </Button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[10rem_12rem_1fr_8rem]">
                        <Input
                            name="type"
                            :default-value="resource.type || 'download'"
                        />
                        <select
                            name="access_level"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option
                                v-for="option in resource_access_options"
                                :key="option.value"
                                :value="option.value"
                                :selected="
                                    option.value === resource.access_level
                                "
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <Input
                            name="external_url"
                            type="url"
                            :default-value="resource.external_url || ''"
                        />
                        <Input
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="resource.sort_order || 0"
                        />
                    </div>

                    <Input
                        name="description"
                        :default-value="resource.description || ''"
                    />

                    <div class="flex flex-wrap items-center gap-5">
                        <input
                            type="hidden"
                            name="file_path"
                            :value="resource.file_path || ''"
                        />
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="hidden"
                                name="is_downloadable"
                                value="0"
                            />
                            <input
                                type="checkbox"
                                name="is_downloadable"
                                value="1"
                                :checked="resource.is_downloadable"
                                class="h-4 w-4 rounded border-input"
                            />
                            Downloadable
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                :checked="resource.is_active"
                                class="h-4 w-4 rounded border-input"
                            />
                            Active
                        </label>
                    </div>
                </Form>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <div class="mb-5 flex items-center gap-2">
                <HelpCircle class="size-5 text-primary" />
                <h2 class="text-lg font-semibold tracking-normal">FAQs</h2>
            </div>

            <Form
                :action="faq_store_url"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-4 border-b pb-5"
            >
                <div class="grid gap-4 md:grid-cols-[1fr_14rem_8rem_auto]">
                    <div class="grid gap-2">
                        <Label for="faq_question">Question</Label>
                        <Input id="faq_question" name="question" required />
                        <InputError :message="errors.question" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="faq_lesson">Lesson</Label>
                        <select
                            id="faq_lesson"
                            name="course_lesson_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">Course level</option>
                            <option
                                v-for="lesson in builder.lessons"
                                :key="lesson.id"
                                :value="lesson.id"
                            >
                                {{ lesson.title }}
                            </option>
                        </select>
                        <InputError :message="errors.course_lesson_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="faq_sort_order">Order</Label>
                        <Input
                            id="faq_sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            default-value="0"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>
                    <Button type="submit" class="mt-7" :disabled="processing">
                        {{ isRevisionMode ? 'Submit addition' : 'Add' }}
                    </Button>
                </div>
                <div class="grid gap-2">
                    <Label for="faq_answer">Answer</Label>
                    <textarea
                        id="faq_answer"
                        name="answer"
                        required
                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.answer" />
                </div>
            </Form>

            <div class="mt-5 grid gap-3">
                <Form
                    v-for="faq in builder.faqs"
                    :key="faq.id"
                    :action="faq.update_url"
                    method="patch"
                    v-slot="{ processing }"
                    class="grid gap-3 rounded-md border p-4"
                >
                    <div
                        class="grid gap-3 md:grid-cols-[1fr_12rem_7rem_auto_auto]"
                    >
                        <Input
                            name="question"
                            :default-value="faq.question"
                            required
                        />
                        <select
                            name="course_lesson_id"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">Course level</option>
                            <option
                                v-for="lesson in builder.lessons"
                                :key="lesson.id"
                                :value="lesson.id"
                                :selected="lesson.id === faq.course_lesson_id"
                            >
                                {{ lesson.title }}
                            </option>
                        </select>
                        <Input
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="faq.sort_order || 0"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteItem(faq.delete_url)"
                        >
                            {{ isRevisionMode ? 'Submit removal' : 'Delete' }}
                        </Button>
                    </div>
                    <textarea
                        name="answer"
                        required
                        class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="faq.answer"
                    />
                </Form>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-normal">
                        Submit course
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Admin will review the curriculum, media, ownership
                        proof, and creator profile.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Form
                        v-if="submit_url"
                        :action="submit_url"
                        method="post"
                        v-slot="{ errors, processing }"
                        class="grid max-w-xl gap-3"
                    >
                        <label
                            class="flex items-start gap-3 text-sm font-semibold text-slate-700"
                        >
                            <input
                                type="checkbox"
                                name="copyright_declaration_accepted"
                                value="1"
                                required
                                class="mt-1 h-4 w-4 rounded border-input"
                            />
                            I confirm this course, lessons, media, resources,
                            and FAQs are original or properly licensed.
                        </label>
                        <InputError
                            :message="errors.copyright_declaration_accepted"
                        />
                        <Button
                            type="submit"
                            :disabled="processing || !canSubmit"
                        >
                            Submit for approval
                        </Button>
                        <InputError class="mt-2" :message="errors.course" />
                    </Form>
                    <Form
                        v-if="delete_request_url"
                        :action="delete_request_url"
                        method="post"
                        v-slot="{ processing }"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Input
                            name="reason"
                            placeholder="Reason for delete request"
                            class="w-72"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Request delete
                        </Button>
                    </Form>
                </div>
            </div>
        </section>
    </div>
</template>
