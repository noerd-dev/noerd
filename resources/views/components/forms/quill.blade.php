<div
    x-data="{
        editor: null,
        content: '',

        init() {
            // Initialize with existing content from data attribute
            this.content = this.$el.dataset.initialContent || '';

            // Wait for Alpine to be ready
            this.$nextTick(() => {
                this.initEditor();
            });
        },

        initEditor() {
            if (typeof window.Quill === 'undefined') {
                console.error('Quill ist nicht geladen');
                return;
            }

            // Initialize Quill
            this.editor = new window.Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder: 'Text eingeben...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['clean']
                    ]
                }
            });

            // Set initial content
            if (this.content) {
                this.editor.root.innerHTML = this.content;
            }

            // Listen for content changes and update Livewire
            this.editor.on('text-change', () => {
                this.content = this.editor.root.innerHTML;
                // Trigger Livewire update
                this.$wire.set('{{ $field }}', this.content);
            });
        }
    }"
    data-initial-content="{{ $content ?? '' }}"
    wire:ignore
>
    <div x-ref="editor" class="quill-container border-0"></div>

    <!-- Hidden input for Livewire -->
    <input type="hidden" wire:model="{{ $field }}" x-model="content">
</div>
