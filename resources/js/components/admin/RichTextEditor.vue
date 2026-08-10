<script setup lang="ts">
import {
    Bold,
    Code2,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    UnderlineIcon,
    Undo2,
} from '@lucide/vue';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import { common, createLowlight } from 'lowlight';
import { onBeforeUnmount, onMounted, shallowRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        name?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        name: undefined,
        placeholder: 'Write lesson content…',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = shallowRef<Editor | null>(null);
const lowlight = createLowlight(common);
const codeLanguages = [
    ['plaintext', 'Plain text'],
    ['php', 'PHP'],
    ['javascript', 'JavaScript'],
    ['typescript', 'TypeScript'],
    ['html', 'HTML'],
    ['css', 'CSS'],
    ['json', 'JSON'],
    ['bash', 'Bash'],
    ['sql', 'SQL'],
] as const;

onMounted(() => {
    editor.value = new Editor({
        content: props.modelValue || '',
        extensions: [
            StarterKit.configure({
                codeBlock: false,
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                    HTMLAttributes: {
                        rel: 'noopener noreferrer nofollow',
                        target: '_blank',
                    },
                },
            }),
            CodeBlockLowlight.configure({ lowlight }),
        ],
        editorProps: {
            attributes: {
                class: 'min-h-64 px-4 py-4 text-base leading-7 outline-none',
                'data-placeholder': props.placeholder,
            },
        },
        onUpdate: ({ editor: currentEditor }) => {
            emit('update:modelValue', currentEditor.getHTML());
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== (value || '')) {
            editor.value.commands.setContent(value || '', {
                emitUpdate: false,
            });
        }
    },
);

function setLink() {
    if (!editor.value) {
        return;
    }

    const previousUrl = editor.value.getAttributes('link').href as
        string | undefined;
    const url = window.prompt('Link URL', previousUrl || 'https://');

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
}

function setCodeLanguage(event: Event) {
    const language = (event.target as HTMLSelectElement).value;

    if (!language) {
        return;
    }

    editor.value?.chain().focus().setCodeBlock({ language }).run();
}

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div class="overflow-hidden rounded-md border bg-background">
        <div
            v-if="editor"
            role="toolbar"
            aria-label="Rich text formatting"
            class="flex min-h-11 flex-wrap items-center gap-1 border-b bg-muted/30 px-2 py-1.5"
        >
            <button
                type="button"
                title="Bold"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="editor.isActive('bold') ? 'bg-muted text-primary' : ''"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </button>
            <button
                type="button"
                title="Italic"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('italic') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
            </button>
            <button
                type="button"
                title="Underline"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('underline') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="size-4" />
            </button>
            <button
                type="button"
                title="Strike"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('strike') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleStrike().run()"
            >
                <Strikethrough class="size-4" />
            </button>
            <span class="mx-1 h-6 w-px bg-border" />
            <button
                type="button"
                title="Heading"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('heading', { level: 2 })
                        ? 'bg-muted text-primary'
                        : ''
                "
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                <Heading2 class="size-4" />
            </button>
            <button
                type="button"
                title="Heading 3"
                aria-label="Heading 3"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('heading', { level: 3 })
                        ? 'bg-muted text-primary'
                        : ''
                "
                @click="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
            >
                <Heading3 class="size-4" />
            </button>
            <button
                type="button"
                title="Bullet list"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('bulletList') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
            </button>
            <button
                type="button"
                title="Numbered list"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('orderedList')
                        ? 'bg-muted text-primary'
                        : ''
                "
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
            </button>
            <button
                type="button"
                title="Quote"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('blockquote') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="size-4" />
            </button>
            <button
                type="button"
                title="Code block"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="
                    editor.isActive('codeBlock') ? 'bg-muted text-primary' : ''
                "
                @click="editor.chain().focus().toggleCodeBlock().run()"
            >
                <Code2 class="size-4" />
            </button>
            <select
                :value="
                    editor.isActive('codeBlock')
                        ? editor.getAttributes('codeBlock').language ||
                          'plaintext'
                        : ''
                "
                title="Code language"
                aria-label="Code language"
                class="h-8 max-w-32 rounded border bg-background px-2 text-xs"
                @change="setCodeLanguage"
            >
                <option value="">Code language</option>
                <option
                    v-for="[value, label] in codeLanguages"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
            <button
                type="button"
                title="Link"
                class="grid size-8 place-items-center rounded hover:bg-muted"
                :class="editor.isActive('link') ? 'bg-muted text-primary' : ''"
                @click="setLink"
            >
                <Link2 class="size-4" />
            </button>
            <span class="mx-1 h-6 w-px bg-border" />
            <button
                type="button"
                title="Undo"
                class="grid size-8 place-items-center rounded hover:bg-muted disabled:opacity-40"
                :disabled="!editor.can().chain().focus().undo().run()"
                @click="editor.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" />
            </button>
            <button
                type="button"
                title="Redo"
                class="grid size-8 place-items-center rounded hover:bg-muted disabled:opacity-40"
                :disabled="!editor.can().chain().focus().redo().run()"
                @click="editor.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" />
            </button>
        </div>

        <EditorContent :editor="editor || undefined" />
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="editor?.getHTML() || ''"
        />
    </div>
</template>

<style scoped>
:deep(.tiptap p.is-editor-empty:first-child::before) {
    float: left;
    height: 0;
    color: var(--muted-foreground);
    content: attr(data-placeholder);
    pointer-events: none;
}

:deep(.tiptap pre) {
    overflow-x: auto;
    border-radius: 6px;
    background: #0f172a;
    padding: 1rem;
    color: #e2e8f0;
}

:deep(.tiptap h2) {
    margin: 1.25rem 0 0.5rem;
    font-size: 1.35rem;
    font-weight: 700;
}

:deep(.tiptap h3) {
    margin: 1rem 0 0.4rem;
    font-size: 1.15rem;
    font-weight: 700;
}

:deep(.tiptap p) {
    margin-bottom: 0.65rem;
}

:deep(.tiptap blockquote) {
    border-left: 3px solid #0f766e;
    padding-left: 1rem;
}

:deep(.tiptap ul),
:deep(.tiptap ol) {
    padding-left: 1.5rem;
}
</style>
