import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { Markdown } from '@tiptap/markdown';
import sort from '@alpinejs/sort';

// Make TipTap globally available
window.TipTap = {
    Editor,
    StarterKit,
    Link,
    Markdown
};

document.addEventListener('alpine:init', () => {
    // Alpine Sort Plugin
    Alpine.plugin(sort);

    // Alpine Stores
    Alpine.store('globalState', {
        open: true,
    });
});
