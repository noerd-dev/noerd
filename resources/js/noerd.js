import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import sort from '@alpinejs/sort';
import focus from '@alpinejs/focus';

// Make TipTap globally available
window.TipTap = {
    Editor,
    StarterKit,
    Link
};

function parseShortcut(shortcut) {
    const parts = shortcut.toLowerCase().split('+').map(p => p.trim());
    const key = parts.pop();
    return {
        key,
        ctrl: parts.includes('ctrl'),
        shift: parts.includes('shift'),
        alt: parts.includes('alt'),
        meta: parts.includes('meta'),
    };
}

function matchesShortcut(event, parsed) {
    if (event.key.toLowerCase() !== parsed.key) return false;
    if (parsed.ctrl && !(event.ctrlKey || event.metaKey)) return false;
    if (parsed.shift && !event.shiftKey) return false;
    if (parsed.alt && !event.altKey) return false;
    if (parsed.meta && !event.metaKey) return false;
    return true;
}

document.addEventListener('alpine:init', () => {
    // Alpine Sort Plugin
    Alpine.plugin(sort);
    Alpine.plugin(focus);

    // Alpine Stores
    Alpine.store('globalState', {
        open: true,
    });

    // Keyboard shortcut support for detail pages
    Alpine.data('noerdPage', ({ currentTab, shortcuts, deleteMessage, hasRecordNav }) => ({
        currentTab,
        hasRecordNav: hasRecordNav || false,
        _parsedShortcuts: {},
        _keydownHandler: null,

        init() {
            for (const [action, str] of Object.entries(shortcuts || {})) {
                this._parsedShortcuts[action] = parseShortcut(str);
            }

            this._keydownHandler = (e) => {
                const tag = document.activeElement?.tagName;
                const inBlockingField = ['TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable;

                // Record navigation: ArrowDown/ArrowUp (blocked only in textarea, select, contenteditable)
                if (this.hasRecordNav && Alpine.store('app').modalOpen && !inBlockingField) {
                    if (e.key === 'ArrowDown' && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                        e.preventDefault();
                        this.$wire.navigateRecord('next');
                        return;
                    }
                    if (e.key === 'ArrowUp' && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                        e.preventDefault();
                        this.$wire.navigateRecord('prev');
                        return;
                    }
                }

                if ('save' in this._parsedShortcuts && matchesShortcut(e, this._parsedShortcuts.save)) {
                    e.preventDefault();
                    this.$wire.store();
                    return;
                }
                if ('delete' in this._parsedShortcuts && matchesShortcut(e, this._parsedShortcuts.delete)) {
                    e.preventDefault();
                    if (window.confirm(deleteMessage)) {
                        this.$wire.delete();
                    }
                }
            };

            window.addEventListener('keydown', this._keydownHandler);
        },

        destroy() {
            if (this._keydownHandler) {
                window.removeEventListener('keydown', this._keydownHandler);
                this._keydownHandler = null;
            }
        },
    }));
});
