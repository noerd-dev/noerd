<div wire:ignore x-data
     x-init="
        let quill = new Quill($refs.editor, {
            theme: 'snow',
             modules: {
              toolbar: [
                ['bold', 'italic', 'underline'],       // inline styles
                [ { 'list': 'bullet' }],
                ['link']                      // no header here
              ]
            }
        });

        // Set initial content from Livewire
        quill.root.innerHTML = @js($content);

        // Update Livewire on change
        quill.on('text-change', function () {
            @this.set('{{$field}}', quill.root.innerHTML);
        });
    "
     wire:ignore>

    <div>
        <div x-ref="editor" style="min-height: 200px;"></div>
    </div>

</div>
