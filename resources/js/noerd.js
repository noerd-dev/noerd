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

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function highlightCode(value) {
    const tokens = [];
    const token = (style, content) => {
        const index = tokens.push(`<span style="${style}">${content}</span>`) - 1;
        return `__NOERD_CODE_TOKEN_${index}__`;
    };

    let html = escapeHtml(value);

    html = html.replace(/(&lt;!--[\s\S]*?--&gt;)/g, (match) => token('color:#6b7280;font-style:italic;', match));
    html = html.replace(/^(\s*)(# .*)$/gm, (match, indent, comment) => indent + token('color:#6b7280;font-style:italic;', comment));
    html = html.replace(/(\/\/.*)$/gm, (match) => token('color:#6b7280;font-style:italic;', match));

    html = html.replace(/(&lt;\/?)([\w:.-]+)([\s\S]*?)(\/?&gt;)/g, (match, open, tagName, attrs, close) => {
        const highlightedAttrs = attrs.replace(
            /([\w:.-]+)(=)(&quot;.*?&quot;|&#039;.*?&#039;)/g,
            (attrMatch, name, equals, attrValue) => `${token('color:#fcd34d;', name)}${equals}${token('color:#6ee7b7;', attrValue)}`
        );

        return `${open}${token('color:#7dd3fc;', tagName)}${highlightedAttrs}${close}`;
    });

    html = html.replace(/(:)([\w:.-]+)(=)/g, (match, prefix, name, equals) => `${prefix}${token('color:#fcd34d;', name)}${equals}`);
    html = html.replace(/(&quot;.*?&quot;|&#039;.*?&#039;)/g, (match) => token('color:#6ee7b7;', match));
    html = html.replace(/(\$[A-Za-z_][A-Za-z0-9_]*)/g, (match) => token('color:#f0abfc;', match));
    html = html.replace(/(@[A-Za-z_][A-Za-z0-9_]*)/g, (match) => token('color:#c4b5fd;', match));
    html = html.replace(/\b(public|function|return|match|array|true|false|null|class|new)\b/g, (match) => token('color:#c4b5fd;', match));
    html = html.replace(/\b(name|label|type|options|fields|tabs|number|colspan|tab|rules|multiple|wire:model)\b(?=:)/g, (match) => token('color:#fcd34d;', match));

    return html.replace(/__NOERD_CODE_TOKEN_(\d+)__/g, (match, index) => tokens[index]);
}

document.addEventListener('alpine:init', () => {
    // Alpine Sort Plugin
    Alpine.plugin(sort);
    Alpine.plugin(focus);

    // Currency input: the bound value stays a plain number, the field shows it
    // formatted with the tenant's separators and accepts either notation.
    Alpine.data('noerdCurrency', ({ name, decSep, thousSep }) => ({
        rawValue: null,
        init() {
            this.rawValue = this.$wire.get(name);
            this.$nextTick(() => this.showFormatted());
        },
        formatDisplay(val) {
            let num = parseFloat(val);
            if (isNaN(num)) num = 0;
            const parts = num.toFixed(2).split('.');
            const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousSep);
            return intPart + decSep + parts[1];
        },
        parseInput(val) {
            if (typeof val === 'number') return val;
            let cleaned = String(val).replace(/\s/g, '');
            if (decSep === ',') {
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else {
                cleaned = cleaned.replace(/,/g, '');
            }
            const num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        },
        showFormatted() {
            this.$refs.input.value = this.formatDisplay(this.rawValue);
        },
        onFocus(e) {
            const num = parseFloat(this.rawValue);
            e.target.value = isNaN(num) ? '' : num.toFixed(2).replace('.', decSep);
            this.$nextTick(() => e.target.select());
        },
        onBlur(e) {
            const parsed = this.parseInput(e.target.value);
            this.rawValue = parsed;
            this.$wire.set(name, parsed);
            this.showFormatted();
        },
    }));

    // Date/time inputs only hold the part the control can show: a datetime
    // string from the model is trimmed to its date (10) or time (5) prefix.
    Alpine.data('noerdDateInput', ({ name, length }) => ({
        init() {
            const value = this.$wire.get(name);
            if (value && value.length > length) {
                this.$wire.set(name, value.substring(0, length), false);
            }
        },
    }));

    Alpine.data('noerdCodeSnippet', () => ({
        copied: false,
        _copyTimer: null,

        highlight(codeElement) {
            if (!codeElement) return;
            codeElement.dataset.rawCode = codeElement.textContent;
            codeElement.innerHTML = highlightCode(codeElement.dataset.rawCode);
        },

        async copy(codeElement) {
            if (!codeElement) return;

            const value = codeElement.dataset.rawCode || codeElement.textContent;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    this.copyWithFallback(value);
                }

                this.showCopiedState();
            } catch (error) {
                this.copyWithFallback(value);
                this.showCopiedState();
            }
        },

        copyWithFallback(value) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        },

        showCopiedState() {
            this.copied = true;
            window.clearTimeout(this._copyTimer);
            this._copyTimer = window.setTimeout(() => {
                this.copied = false;
            }, 1500);
        },
    }));

    // Keyboard shortcut support for detail pages
    Alpine.data('noerdPage', ({ currentTab, shortcuts, deleteMessage }) => ({
        currentTab,
        _parsedShortcuts: {},
        _keydownHandler: null,

        init() {
            for (const [action, str] of Object.entries(shortcuts || {})) {
                this._parsedShortcuts[action] = parseShortcut(str);
            }

            this._keydownHandler = (e) => {
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
