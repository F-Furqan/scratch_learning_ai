<script setup lang="ts">
import { FileText, Sparkles } from '@lucide/vue';

import RichContent from '@/components/public/RichContent.vue';
import SeoHead from '@/components/public/SeoHead.vue';

import type { CmsPage, SeoPayload } from '@/types';

defineProps<{
    page: CmsPage;
    seo: SeoPayload;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <article>
        <section class="relative bg-[#101827] text-white">
            <div
                class="absolute inset-0 bg-[linear-gradient(135deg,#101827,#0f766e_52%,#f59e0b)] opacity-85"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,24,39,0.98),rgba(16,24,39,0.84),rgba(16,24,39,0.5))]"
            />
            <div class="relative mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
                <p
                    class="inline-flex items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-black text-teal-100"
                >
                    <FileText class="h-4 w-4 text-amber-300" />
                    {{ page.template || 'Page' }}
                </p>
                <h1
                    class="mt-5 text-5xl leading-tight font-black tracking-normal sm:text-6xl"
                >
                    {{ page.title }}
                </h1>
                <p
                    v-if="page.excerpt"
                    class="mt-5 max-w-3xl text-lg leading-8 text-slate-200"
                >
                    {{ page.excerpt }}
                </p>
            </div>
        </section>

        <section class="bg-white py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <RichContent
                    v-if="page.content"
                    class="rounded-md border border-slate-200 bg-white p-6 text-base leading-8 whitespace-pre-line text-slate-700 shadow-[0_16px_45px_rgba(15,23,42,0.06)]"
                    :html="page.content"
                />

                <div v-if="page.blocks.length" class="mt-8 grid gap-5">
                    <section
                        v-for="block in page.blocks"
                        :key="block.id"
                        class="rounded-md border border-slate-200 bg-[#f7f8fb] p-6"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-white text-teal-700 shadow-sm"
                            >
                                <Sparkles class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <h2
                                    v-if="block.title"
                                    class="text-2xl font-black tracking-normal text-slate-950"
                                >
                                    {{ block.title }}
                                </h2>
                                <RichContent
                                    v-if="block.body"
                                    class="mt-3 leading-8 whitespace-pre-line text-slate-700"
                                    :html="block.body"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </article>
</template>
