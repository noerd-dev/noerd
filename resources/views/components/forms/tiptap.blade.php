@props(['field', 'content' => ''])

<div
    x-data="{
        editor: null,
        content: @js($content),
        linkUrl: '',
        showLinkInput: false,
        updatedAt: Date.now(),
        init() {
            this.editor = new window.TipTap.Editor({
                element: this.$refs.editor,
                extensions: [
                    window.TipTap.StarterKit.configure({
                        heading: {
                            levels: [1, 2, 3],
                        },
                    }),
                    window.TipTap.Link.configure({
                        openOnClick: false,
                    }),
                    window.TipTap.Markdown.configure({
                        html: false,
                    }),
                ],
                content: this.content,
                contentType: 'markdown',
                editorProps: {
                    attributes: {
                        class: 'rich-text focus:outline-none min-h-[150px] p-3',
                    },
                },
                onUpdate: ({ editor }) => {
                    this.content = editor.getMarkdown();
                    this.updatedAt = Date.now();
                    this.$wire.set('{{ $field }}', this.content);
                },
                onSelectionUpdate: () => {
                    this.updatedAt = Date.now();
                },
            });
        },
        isActive(type, attrs = {}) {
            return this.updatedAt && Alpine.raw(this.editor)?.isActive(type, attrs);
        },
        setLink() {
            if (this.linkUrl) {
                Alpine.raw(this.editor).chain().focus().setLink({ href: this.linkUrl }).run();
            }
            this.linkUrl = '';
            this.showLinkInput = false;
        },
        removeLink() {
            Alpine.raw(this.editor).chain().focus().unsetLink().run();
        }
    }"
    wire:ignore
    class="tiptap-wrapper"
>
    {{-- Toolbar --}}
    <template x-if="editor">
        <div class="flex flex-wrap items-center gap-0.5 border border-b-0 border-gray-300 rounded-t-md bg-gray-50 p-1">
            {{-- Headings --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-gray-200': isActive('heading', { level: 1 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                title="Heading 1 (# )"
            >H1</button>
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-gray-200': isActive('heading', { level: 2 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                title="Heading 2 (## )"
            >H2</button>
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleHeading({ level: 3 }).run()"
                :class="{ 'bg-gray-200': isActive('heading', { level: 3 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                title="Heading 3 (### )"
            >H3</button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            {{-- Bold --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleBold().run()"
                :class="{ 'bg-gray-200': isActive('bold') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Bold (**text**)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                </svg>
            </button>

            {{-- Italic --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleItalic().run()"
                :class="{ 'bg-gray-200': isActive('italic') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Italic (*text*)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"></path>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            {{-- Bullet List --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleBulletList().run()"
                :class="{ 'bg-gray-200': isActive('bulletList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Bullet List (- item)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            {{-- Ordered List --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-gray-200': isActive('orderedList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Numbered List (1. item)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"></path>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            {{-- Blockquote --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-gray-200': isActive('blockquote') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Quote (> text)"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z"/>
                </svg>
            </button>

            {{-- Horizontal Rule --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().setHorizontalRule().run()"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Horizontal Line (---)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            {{-- Link --}}
            <div class="relative">
                <button
                    type="button"
                    @click.prevent="showLinkInput = !showLinkInput"
                    :class="{ 'bg-gray-200': isActive('link') }"
                    class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                    title="Link ([text](url))"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </button>
                <div
                    x-show="showLinkInput"
                    x-cloak
                    @click.outside="showLinkInput = false"
                    class="absolute top-full left-0 mt-1 p-2 bg-white border border-gray-300 rounded-md shadow-lg z-10 flex gap-2"
                >
                    <input
                        type="url"
                        x-model="linkUrl"
                        placeholder="https://..."
                        class="text-sm border border-gray-300 rounded px-2 py-1 w-48"
                        @keydown.enter.prevent="setLink()"
                    >
                    <button
                        type="button"
                        @click.prevent="setLink()"
                        class="text-sm bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600"
                    >OK</button>
                    <button
                        type="button"
                        x-show="isActive('link')"
                        @click.prevent="removeLink(); showLinkInput = false"
                        class="text-sm bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
                    >X</button>
                </div>
            </div>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            {{-- Code --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleCode().run()"
                :class="{ 'bg-gray-200': isActive('code') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Inline Code (`code`)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
            </button>

            <div class="flex-1"></div>

            {{-- Clear Formatting --}}
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().unsetAllMarks().clearNodes().run()"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-gray-500"
                title="Clear Formatting"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </template>

    {{-- Editor Area --}}
    <div x-ref="editor" class="border border-gray-300 rounded-b-md bg-white"></div>
</div>
