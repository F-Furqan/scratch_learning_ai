<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';

import RichTextEditor from '@/components/admin/RichTextEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = {
    id: number;
    name: string;
    course_category_id?: number;
};

type ContentItem = {
    id: number;
    title: string;
    category_id?: number | null;
    subcategory_id?: number | null;
    excerpt?: string | null;
    content?: string | null;
    short_description?: string | null;
    description?: string | null;
    intro_video_url?: string | null;
    level?: string | null;
    language?: string | null;
    price?: string | null;
    is_free?: boolean;
    seo_title?: string | null;
    seo_description?: string | null;
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

const props = defineProps<{
    kind: 'blog' | 'course';
    title: string;
    item: ContentItem | null;
    categories: Option[];
    subcategories?: Option[];
    store_url: string;
    update_url?: string;
    submit_url?: string;
    delete_request_url?: string;
    cancel_url: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator content',
                href: '/creator/dashboard',
            },
        ],
    },
});

const isCourse = props.kind === 'course';
const isRevisionMode =
    props.kind === 'blog' && props.item?.status === 'published';
const action = props.item ? props.update_url : props.store_url;
const method = props.item ? 'patch' : 'post';
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    {{ title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        isRevisionMode
                            ? 'Submitted changes become a revision for admin review.'
                            : 'Draft now, submit when ready for admin review.'
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
            v-if="item?.rejection_reason || item?.admin_notes"
            class="rounded-lg border border-rose-200 bg-rose-50 p-5 text-rose-950"
        >
            <h2 class="font-semibold">Reviewer notes</h2>
            <p class="mt-1 text-sm">
                {{ item?.rejection_reason || item?.admin_notes }}
            </p>
        </section>

        <section
            v-if="item?.active_revision"
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

        <section class="rounded-lg border bg-card p-6">
            <Form
                v-if="action"
                :action="action"
                :method="method"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        :default-value="item?.title || ''"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label
                        :for="
                            isCourse ? 'course_category_id' : 'blog_category_id'
                        "
                    >
                        Category
                    </Label>
                    <select
                        :id="
                            isCourse ? 'course_category_id' : 'blog_category_id'
                        "
                        :name="
                            isCourse ? 'course_category_id' : 'blog_category_id'
                        "
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    >
                        <option value="">Uncategorized</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                            :selected="category.id === item?.category_id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError
                        :message="
                            isCourse
                                ? errors.course_category_id
                                : errors.blog_category_id
                        "
                    />
                </div>

                <div v-if="isCourse" class="grid gap-2">
                    <Label for="course_subcategory_id">Subcategory</Label>
                    <select
                        id="course_subcategory_id"
                        name="course_subcategory_id"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    >
                        <option value="">No subcategory</option>
                        <option
                            v-for="subcategory in subcategories || []"
                            :key="subcategory.id"
                            :value="subcategory.id"
                            :selected="subcategory.id === item?.subcategory_id"
                        >
                            {{ subcategory.name }}
                        </option>
                    </select>
                    <InputError :message="errors.course_subcategory_id" />
                </div>

                <div class="grid gap-2">
                    <Label :for="isCourse ? 'short_description' : 'excerpt'">
                        {{ isCourse ? 'Short description' : 'Excerpt' }}
                    </Label>
                    <textarea
                        :id="isCourse ? 'short_description' : 'excerpt'"
                        :name="isCourse ? 'short_description' : 'excerpt'"
                        class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="
                            isCourse
                                ? item?.short_description || ''
                                : item?.excerpt || ''
                        "
                    />
                    <InputError
                        :message="
                            isCourse ? errors.short_description : errors.excerpt
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label :for="isCourse ? 'description' : 'content'">
                        {{ isCourse ? 'Course description' : 'Blog content' }}
                    </Label>
                    <RichTextEditor
                        :name="isCourse ? 'description' : 'content'"
                        :model-value="
                            isCourse
                                ? item?.description || ''
                                : item?.content || ''
                        "
                        :placeholder="
                            isCourse
                                ? 'Write the course description…'
                                : 'Write the article…'
                        "
                    />
                    <InputError
                        :message="
                            isCourse ? errors.description : errors.content
                        "
                    />
                </div>

                <template v-if="isCourse">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="level">Level</Label>
                            <Input
                                id="level"
                                name="level"
                                :default-value="item?.level || 'intermediate'"
                            />
                            <InputError :message="errors.level" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="language">Language</Label>
                            <Input
                                id="language"
                                name="language"
                                :default-value="item?.language || 'en'"
                            />
                            <InputError :message="errors.language" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="intro_video_url">Intro video URL</Label>
                        <Input
                            id="intro_video_url"
                            name="intro_video_url"
                            type="url"
                            :default-value="item?.intro_video_url || ''"
                        />
                        <InputError :message="errors.intro_video_url" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-[1fr_auto]">
                        <div class="grid gap-2">
                            <Label for="price">Price</Label>
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                min="0"
                                step="0.01"
                                :default-value="item?.price || '0'"
                            />
                            <InputError :message="errors.price" />
                        </div>
                        <label class="mt-7 flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_free" value="0" />
                            <input
                                type="checkbox"
                                name="is_free"
                                value="1"
                                :checked="item?.is_free || false"
                                class="h-4 w-4 rounded border-input"
                            />
                            Free course
                        </label>
                    </div>
                </template>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="seo_title">SEO title</Label>
                        <Input
                            id="seo_title"
                            name="seo_title"
                            :default-value="item?.seo_title || ''"
                        />
                        <InputError :message="errors.seo_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="seo_description">SEO description</Label>
                        <Input
                            id="seo_description"
                            name="seo_description"
                            :default-value="item?.seo_description || ''"
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
                        I confirm this revision is original or properly
                        licensed, and I have rights to submit it for Scratch
                        Learning review.
                    </label>
                    <InputError
                        class="mt-2"
                        :message="errors.copyright_declaration_accepted"
                    />
                </div>

                <div class="flex flex-wrap gap-3 border-t pt-5">
                    <Button type="submit" :disabled="processing">
                        {{ isRevisionMode ? 'Submit revision' : 'Save draft' }}
                    </Button>
                </div>
            </Form>

            <div v-if="item" class="mt-5 flex flex-wrap gap-3 border-t pt-5">
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
                        I confirm this content is original or properly licensed,
                        and I have rights to publish it on Scratch Learning.
                    </label>
                    <InputError
                        :message="errors.copyright_declaration_accepted"
                    />
                    <Button type="submit" :disabled="processing">
                        Submit for approval
                    </Button>
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
        </section>
    </div>
</template>
