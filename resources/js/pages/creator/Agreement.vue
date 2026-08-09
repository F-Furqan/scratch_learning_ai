<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, FileCheck2 } from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    agreement: {
        version: string;
        accepted: boolean;
        terms_url: string;
        privacy_url: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator agreement',
                href: '/creator/agreement',
            },
        ],
    },
});
</script>

<template>
    <Head title="Creator Agreement" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <section class="rounded-lg border bg-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p
                        class="inline-flex items-center gap-2 rounded-md bg-teal-100 px-3 py-1 text-xs font-semibold tracking-normal text-teal-800 uppercase"
                    >
                        <FileCheck2 class="h-4 w-4" />
                        Version {{ agreement.version }}
                    </p>
                    <h1 class="mt-4 text-2xl font-semibold tracking-normal">
                        Creator Agreement
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Accept this agreement before uploading or submitting
                        creator content for admin review.
                    </p>
                </div>
                <span
                    v-if="agreement.accepted"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-800"
                >
                    <CheckCircle2 class="h-4 w-4" />
                    Accepted
                </span>
            </div>
        </section>

        <section class="grid gap-4 rounded-lg border bg-card p-6">
            <div class="grid gap-3 text-sm leading-7 text-muted-foreground">
                <p>
                    Scratch Learning does not pay creators, instructors, or
                    bloggers for uploaded or published content under this
                    creator program.
                </p>
                <p>
                    Published creator content is promotional and educational.
                    Creator LinkedIn, website, or public profile links may be
                    shown on blogs, courses, lesson pages, and creator profiles.
                </p>
                <p>
                    You confirm that you own, created, licensed, or otherwise
                    have the required rights to submit every course, lesson,
                    video, image, document, blog, and resource you upload.
                </p>
                <p>
                    Review the full
                    <Link
                        :href="agreement.terms_url"
                        class="font-semibold text-teal-700 underline underline-offset-4"
                    >
                        Terms & Conditions
                    </Link>
                    and
                    <Link
                        :href="agreement.privacy_url"
                        class="font-semibold text-teal-700 underline underline-offset-4"
                    >
                        Privacy Policy
                    </Link>
                    before accepting.
                </p>
            </div>

            <Form
                action="/creator/agreement"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-4 border-t pt-5"
            >
                <label class="flex items-start gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="creator_agreement_accepted"
                        value="1"
                        required
                        class="mt-1 h-4 w-4 rounded border-input text-teal-600 focus:ring-teal-600"
                    />
                    <span>
                        I accept the current creator agreement and confirm the
                        content ownership, promotional display, LinkedIn/profile
                        link, and no-payment terms.
                    </span>
                </label>
                <InputError :message="errors.creator_agreement_accepted" />

                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="processing"
                    data-test="accept-creator-agreement-button"
                >
                    Accept creator agreement
                </Button>
            </Form>
        </section>
    </div>
</template>
