<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Award, CheckCircle2, XCircle } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import type { SeoPayload } from '@/types';

defineProps<{
    certificate: {
        certificate_number: string;
        student_name: string | null;
        course_title: string | null;
        course_url: string | null;
        status: string;
        is_valid: boolean;
        issued_at: string | null;
        expires_at: string | null;
    };
    seo: SeoPayload;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex items-start gap-4">
                <div
                    class="grid h-12 w-12 place-items-center rounded-md bg-white text-slate-950 shadow-sm"
                >
                    <Award class="h-6 w-6" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold tracking-normal text-teal-700 uppercase"
                    >
                        Certificate Verification
                    </p>
                    <h1
                        class="mt-2 text-4xl font-semibold tracking-normal text-slate-950"
                    >
                        {{ certificate.certificate_number }}
                    </h1>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-md border border-slate-200 p-6">
                <div class="flex items-center gap-3">
                    <CheckCircle2
                        v-if="certificate.is_valid"
                        class="h-6 w-6 text-emerald-600"
                    />
                    <XCircle v-else class="h-6 w-6 text-rose-600" />
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">
                            {{
                                certificate.is_valid
                                    ? 'Valid certificate'
                                    : 'Certificate not valid'
                            }}
                        </h2>
                        <p class="text-sm text-slate-600">
                            Status: {{ certificate.status }}
                        </p>
                    </div>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">
                            Student
                        </dt>
                        <dd class="mt-1 text-slate-950">
                            {{ certificate.student_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">
                            Course
                        </dt>
                        <dd class="mt-1 text-slate-950">
                            <Link
                                v-if="certificate.course_url"
                                :href="certificate.course_url"
                                class="font-medium text-teal-700 hover:text-teal-900"
                            >
                                {{ certificate.course_title }}
                            </Link>
                            <span v-else>{{ certificate.course_title }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">
                            Issued
                        </dt>
                        <dd class="mt-1 text-slate-950">
                            {{
                                certificate.issued_at
                                    ? new Date(
                                          certificate.issued_at,
                                      ).toLocaleDateString()
                                    : 'Not issued'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">
                            Expires
                        </dt>
                        <dd class="mt-1 text-slate-950">
                            {{
                                certificate.expires_at
                                    ? new Date(
                                          certificate.expires_at,
                                      ).toLocaleDateString()
                                    : 'No expiry'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
