<script setup lang="ts">
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import plaintext from 'highlight.js/lib/languages/plaintext';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import 'highlight.js/styles/atom-one-dark.css';
import { nextTick, onMounted, onUpdated, ref, watch } from 'vue';

const props = defineProps<{
    html: string;
}>();

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('css', css);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);
hljs.registerLanguage('plaintext', plaintext);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);

const container = ref<HTMLElement | null>(null);

async function copyText(value: string) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);

        return;
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', '');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.append(input);
    input.select();
    document.execCommand('copy');
    input.remove();
}

async function enhanceCodeBlocks() {
    await nextTick();

    container.value?.querySelectorAll('pre').forEach((block) => {
        const code = block.querySelector<HTMLElement>('code');

        if (code && !code.dataset.highlighted) {
            hljs.highlightElement(code);
        }

        if (block.querySelector(':scope > .sl-code-toolbar')) {
            return;
        }

        const languageClass = Array.from(code?.classList || []).find((name) =>
            name.startsWith('language-'),
        );
        const language = languageClass?.replace('language-', '') || 'code';
        const toolbar = document.createElement('div');
        toolbar.className = 'sl-code-toolbar';

        const label = document.createElement('span');
        label.className = 'sl-code-language';
        label.textContent = language;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sl-code-copy';
        button.textContent = 'Copy';
        button.setAttribute('aria-label', 'Copy code');
        button.addEventListener('click', async () => {
            const codeText = block.querySelector('code')?.textContent || '';
            await copyText(codeText);
            button.textContent = 'Copied';
            window.setTimeout(() => {
                button.textContent = 'Copy';
            }, 1600);
        });

        toolbar.append(label, button);
        block.prepend(toolbar);
    });
}

onMounted(enhanceCodeBlocks);
onUpdated(enhanceCodeBlocks);
watch(() => props.html, enhanceCodeBlocks);
</script>

<template>
    <div ref="container" class="sl-rich-content" v-html="html" />
</template>

<style scoped>
.sl-rich-content {
    color: #334155;
    font-size: 0.975rem;
    line-height: 1.75;
}

.sl-rich-content :deep(p),
.sl-rich-content :deep(ul),
.sl-rich-content :deep(ol),
.sl-rich-content :deep(blockquote),
.sl-rich-content :deep(pre) {
    margin: 0 0 1rem;
}

.sl-rich-content :deep(h2),
.sl-rich-content :deep(h3),
.sl-rich-content :deep(h4) {
    margin: 1.5rem 0 0.65rem;
    color: #0f172a;
    font-weight: 700;
    letter-spacing: 0;
}

.sl-rich-content :deep(ul),
.sl-rich-content :deep(ol) {
    padding-left: 1.5rem;
}

.sl-rich-content :deep(blockquote) {
    border-left: 3px solid #0f766e;
    padding-left: 1rem;
    color: #475569;
}

.sl-rich-content :deep(a) {
    color: #0f766e;
    text-decoration: underline;
}

.sl-rich-content :deep(pre) {
    position: relative;
    overflow-x: auto;
    border: 1px solid #1e293b;
    border-radius: 6px;
    background: #0f172a;
    padding: 3.5rem 1rem 1rem;
    color: #e2e8f0;
}

.sl-rich-content :deep(.sl-code-toolbar) {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    display: flex;
    min-height: 2.6rem;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #334155;
    background: #111827;
    padding: 0.45rem 0.65rem 0.45rem 0.9rem;
}

.sl-rich-content :deep(.sl-code-language) {
    color: #94a3b8;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.sl-rich-content :deep(code) {
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.875rem;
}

.sl-rich-content :deep(.sl-code-copy) {
    border: 1px solid #475569;
    border-radius: 4px;
    background: #1e293b;
    padding: 0.3rem 0.6rem;
    color: #f8fafc;
    font-size: 0.75rem;
    font-weight: 600;
}

.sl-rich-content :deep(.sl-code-copy:hover) {
    border-color: #5eead4;
    color: #ccfbf1;
}

.sl-rich-content :deep(:not(pre) > code) {
    border-radius: 4px;
    background: #f1f5f9;
    padding: 0.12rem 0.3rem;
    color: #be123c;
}
</style>
