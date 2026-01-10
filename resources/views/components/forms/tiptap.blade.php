@props(['field', 'content' => ''])

<div
    x-data="{
        editor: null,
        content: @js($content),
        updatedAt: Date.now(),
        init() {
            this.editor = new window.TipTap.Editor({
                element: this.$refs.editor,
                extensions: [
                    window.TipTap.StarterKit.configure({
                        heading: false,
                        bulletList: false,
                        orderedList: false,
                        blockquote: false,
                        codeBlock: false,
                        horizontalRule: false,
                    }),
                    window.TipTap.Markdown.configure({
                        html: false,
                        transformPastedText: true,
                        transformCopiedText: true,
                    }),
                ],
                content: this.content,
                contentType: 'markdown',
                editorProps: {
                    attributes: {
                        class: 'prose prose-sm max-w-none focus:outline-none min-h-[100px] p-3',
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
                onTransaction: () => {
                    this.updatedAt = Date.now();
                },
            });
        },
        isActive(type) {
            return this.updatedAt && Alpine.raw(this.editor)?.isActive(type);
        }
    }"
    wire:ignore
    class="tiptap-wrapper"
>
    {{-- Toolbar --}}
    <template x-if="editor">
        <div class="flex gap-1 border border-b-0 border-gray-300 rounded-t-md bg-gray-50 p-1">
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleBold().run()"
                :class="{ 'bg-gray-200': isActive('bold') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Bold"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                </svg>
            </button>
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().toggleItalic().run()"
                :class="{ 'bg-gray-200': isActive('italic') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Italic"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"></path>
                </svg>
            </button>
            <div class="w-px bg-gray-300 mx-1"></div>
            <button
                type="button"
                @click.prevent="Alpine.raw(editor).chain().focus().unsetAllMarks().run()"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
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
