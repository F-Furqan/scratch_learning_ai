<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { FileWarning, Scale, ShieldCheck } from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="Copyright Takedown Request" />

    <section class="relative bg-[#101827] text-white">
        <div
            class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#7f1d1d_52%,#0f766e)] opacity-85"
        />
        <div
            class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.86),rgba(16,24,39,0.48))]"
        />
        <div class="relative mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
            <p
                class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-amber-100"
            >
                <Scale class="h-4 w-4 text-amber-300" />
                Legal safety
            </p>
            <h1 class="mt-5 text-5xl leading-tight font-black tracking-normal">
                Copyright takedown request
            </h1>
            <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-200">
                Report content that you believe infringes your copyright. The
                admin review team will evaluate the request and preserve an
                auditable decision record.
            </p>
        </div>
    </section>

    <section class="bg-white py-12">
        <div
            class="mx-auto grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8"
        >
            <Form
                action="/copyright/takedown"
                method="post"
                v-slot="{ errors, processing, recentlySuccessful }"
                class="grid gap-5 rounded-md border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)]"
            >
                <div>
                    <h2
                        class="text-2xl font-black tracking-normal text-slate-950"
                    >
                        Request details
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">
                        Complete every required field so the review team can
                        verify ownership and locate the reported content.
                    </p>
                </div>

                <div
                    v-if="recentlySuccessful"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900"
                >
                    Request submitted. Scratch Learning will review the report.
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="claimant_name">Your name</Label>
                        <Input
                            id="claimant_name"
                            name="claimant_name"
                            required
                        />
                        <InputError :message="errors.claimant_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="claimant_email">Email</Label>
                        <Input
                            id="claimant_email"
                            name="claimant_email"
                            type="email"
                            required
                        />
                        <InputError :message="errors.claimant_email" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="claimant_company">Company</Label>
                        <Input id="claimant_company" name="claimant_company" />
                        <InputError :message="errors.claimant_company" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rights_owner">Rights owner</Label>
                        <Input id="rights_owner" name="rights_owner" required />
                        <InputError :message="errors.rights_owner" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="infringing_url"
                        >Scratch Learning content URL</Label
                    >
                    <Input
                        id="infringing_url"
                        name="infringing_url"
                        type="url"
                        required
                        placeholder="https://scratch_learning_final.test/blog/example"
                    />
                    <InputError :message="errors.infringing_url" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="original_work_url">Original work URL</Label>
                        <Input
                            id="original_work_url"
                            name="original_work_url"
                            type="url"
                        />
                        <InputError :message="errors.original_work_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="content_title"
                            >Reported content title</Label
                        >
                        <Input id="content_title" name="content_title" />
                        <InputError :message="errors.content_title" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description"
                        >Describe the copyright issue</Label
                    >
                    <textarea
                        id="description"
                        name="description"
                        required
                        class="min-h-36 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div
                    class="grid gap-3 rounded-md border border-slate-200 bg-[#f7f8fb] p-4"
                >
                    <label
                        class="flex items-start gap-3 text-sm font-semibold text-slate-700"
                    >
                        <input
                            type="checkbox"
                            name="good_faith_confirmed"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-input"
                        />
                        I have a good-faith belief that the disputed use is not
                        authorized by the copyright owner, its agent, or the
                        law.
                    </label>
                    <InputError :message="errors.good_faith_confirmed" />

                    <label
                        class="flex items-start gap-3 text-sm font-semibold text-slate-700"
                    >
                        <input
                            type="checkbox"
                            name="accuracy_confirmed"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-input"
                        />
                        I confirm this notice is accurate and that I am the
                        owner or authorized to act for the owner.
                    </label>
                    <InputError :message="errors.accuracy_confirmed" />
                </div>

                <div class="grid gap-2">
                    <Label for="signature">Electronic signature</Label>
                    <Input id="signature" name="signature" required />
                    <InputError :message="errors.signature" />
                </div>

                <Button type="submit" class="w-fit" :disabled="processing">
                    Submit takedown request
                </Button>
            </Form>

            <aside class="grid h-max gap-4">
                <div
                    class="rounded-md border border-slate-200 bg-[#f7f8fb] p-5"
                >
                    <ShieldCheck class="h-6 w-6 text-teal-700" />
                    <h2
                        class="mt-4 text-lg font-black tracking-normal text-slate-950"
                    >
                        What happens next
                    </h2>
                    <ul
                        class="mt-3 grid gap-2 text-sm leading-6 font-semibold text-slate-600"
                    >
                        <li>
                            Admin reviewers verify the report and linked
                            content.
                        </li>
                        <li>
                            Every decision saves reviewer, notes, status, and
                            time.
                        </li>
                        <li>
                            Creators may be contacted if more information is
                            needed.
                        </li>
                    </ul>
                </div>
                <div
                    class="rounded-md border border-amber-200 bg-amber-50 p-5 text-amber-950"
                >
                    <FileWarning class="h-6 w-6" />
                    <h2 class="mt-4 text-lg font-black tracking-normal">
                        Need general support?
                    </h2>
                    <p class="mt-2 text-sm leading-6 font-semibold">
                        For account, course, or payment questions, use the
                        normal contact page instead.
                    </p>
                    <Link
                        href="/contact-us"
                        class="mt-4 inline-flex rounded-md bg-white px-4 py-2 text-sm font-black text-amber-900"
                    >
                        Contact support
                    </Link>
                </div>
            </aside>
        </div>
    </section>
</template>
