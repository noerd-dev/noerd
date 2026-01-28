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
    // Modal magic
    Alpine.magic('modal', () => {
        return (component, args = {}, source = null) => {
            const params = { modalComponent: component, arguments: args };
            if (source) params.source = source;
            Livewire.dispatch('noerdModal', params);
        };
    });

    // Alpine Sort Plugin
    Alpine.plugin(sort);

    // Alpine Stores
    Alpine.store('globalState', {
        open: true,
    });

    Alpine.store('app', {
        currentId: null,
        modalOpen: false,
        setId(id) {
            this.currentId = id;
        }
    });
});

document.addEventListener('set-app-id', (event) => {
    Alpine.store('app').setId(event.detail.id);
});

document.addEventListener('modal-closed-global', () => {
    Alpine.store('app').modalOpen = false;
});
