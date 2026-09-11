import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import sort from '@alpinejs/sort';
import focus from '@alpinejs/focus';

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

/**
 * Is `el` on the layer the user is actually looking at?
 *
 * Every page, detail and list behind an open modal stays mounted and keeps its
 * `window` keydown listener, so a shortcut used to fire on all of them at once:
 * one ctrl+enter saved the record under the modal as well, and in a stacked
 * review it decided the proposal underneath too.
 *
 * The topmost layer is the LAST modal panel in the document — the modal stack
 * teleports its panels to <body> in open order, the same contract its own
 * "close the top modal" handling relies on. With no modal open (or without the
 * modal package installed) the page itself is the top layer.
 */
function isTopLayer(el) {
    const panels = document.querySelectorAll('[modal]');

    if (panels.length === 0) {
        return true;
    }

    return panels[panels.length - 1].contains(el);
}

/**
 * The same check for Blade views with their own `@keydown.window`. It is a
 * plain global rather than an Alpine magic on purpose: a published asset bundle
 * that is older than the views (the classic `vendor:publish` miss) then simply
 * has no guard — an unknown magic would throw on every keystroke instead.
 * Views therefore call it as `(window.noerdTopLayer?.($el) ?? true)`.
 */
window.noerdTopLayer = isTopLayer;

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

    // Currency input: the bound value stays a plain number — or null. The
    // field shows it formatted with the tenant's separators and accepts either
    // notation. An empty input means "no amount", never 0, so an optional
    // amount column stays NULL and a form never shows a value it does not hold.
    Alpine.data('noerdCurrency', ({ name, decSep, thousSep }) => ({
        rawValue: null,
        init() {
            this.rawValue = this.normalize(this.$wire.get(name));
            this.$nextTick(() => this.showFormatted());

            // The input is wire:ignore, so a value the SERVER changes after
            // mount (figures derived on save, a proposal loaded into the form)
            // has to be written into it by hand. Skip echoes of our own updates
            // and never overwrite what the reader is typing right now.
            this.$wire.$watch(name, (value) => {
                const next = this.normalize(value);
                if (next === this.rawValue || document.activeElement === this.$refs.input) {
                    return;
                }
                this.rawValue = next;
                this.showFormatted();
            });
        },
        normalize(value) {
            if (value === null || value === undefined || value === '') return null;
            const num = typeof value === 'number' ? value : parseFloat(value);
            return isNaN(num) ? null : num;
        },
        formatDisplay(val) {
            if (val === null) return '';
            const parts = val.toFixed(2).split('.');
            const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousSep);
            return intPart + decSep + parts[1];
        },
        parseInput(val) {
            if (typeof val === 'number') return val;
            // ICU grouping may be ".", ",", "’" or a (narrow) no-break space:
            // keep digits, the sign and the locale's decimal separator only.
            const escapedDecSep = decSep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const cleaned = String(val)
                .replace(new RegExp('[^0-9\\-' + escapedDecSep + ']', 'g'), '')
                .replace(decSep, '.');
            const num = parseFloat(cleaned);
            return isNaN(num) ? null : num;
        },
        showFormatted() {
            this.$refs.input.value = this.formatDisplay(this.rawValue);
        },
        onFocus(e) {
            e.target.value = this.rawValue === null ? '' : this.rawValue.toFixed(2).replace('.', decSep);
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

    // The application shell: sidebar/app-bar visibility with a desktop
    // breakpoint that remembers the desktop layout across mobile detours.
    Alpine.data('noerdAppShell', ({ showSidebar, showAppbar }) => ({
        openProfile: false,
        isModal: false,
        selectedRow: 0,
        activeList: '',
        isDesktop: window.innerWidth >= 1024,
        desktopSidebar: showSidebar,
        desktopAppbar: showAppbar,
        showSidebar: window.innerWidth >= 1024 ? showSidebar : false,
        showAppbar: window.innerWidth >= 1024 ? showAppbar : false,

        handleResize() {
            const desktop = window.innerWidth >= 1024;
            if (desktop === this.isDesktop) return;
            this.isDesktop = desktop;
            if (desktop) {
                this.showSidebar = this.desktopSidebar;
                this.showAppbar = this.desktopAppbar;
            } else {
                this.desktopSidebar = this.showSidebar;
                this.desktopAppbar = this.showAppbar;
                this.showSidebar = false;
                this.showAppbar = false;
            }
        },
    }));

    // Drag-resizable navigation column. The width lives in a CSS custom
    // property while dragging and is persisted to the component on release;
    // the handle is also operable from the keyboard.
    Alpine.data('noerdSidebarResize', ({ min = 200, max = 500, step = 16 } = {}) => ({
        isResizing: false,
        startX: 0,
        startWidth: 0,
        width: 0,

        init() {
            this.width = this.currentWidth();
        },
        currentWidth() {
            return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-nav-width'), 10) || min;
        },
        applyWidth(value) {
            this.width = Math.max(min, Math.min(max, Math.round(value)));
            document.documentElement.style.setProperty('--sidebar-nav-width', this.width + 'px');
        },
        start(event) {
            this.isResizing = true;
            this.startX = event.clientX;
            this.startWidth = this.currentWidth();
        },
        move(event) {
            if (! this.isResizing) return;
            this.applyWidth(this.startWidth + (event.clientX - this.startX));
        },
        stop() {
            if (! this.isResizing) return;
            this.isResizing = false;
            this.persist();
        },
        nudge(direction) {
            this.applyWidth(this.currentWidth() + direction * step);
            this.persist();
        },
        persist() {
            this.$wire.saveSidebarWidth(this.currentWidth() + 'px');
        },
    }));

    // Keyboard navigation of a list: arrow keys move the highlighted row,
    // Enter opens it. Scoped per list so nested lists never fight over it.
    Alpine.data('noerdList', ({ listId }) => ({
        selectedRow: 0,

        init() {
            this.$store.app.setId(listId);
        },
        claim() {
            this.$store.app.setId(listId);
        },
        isInBlockingField() {
            const el = document.activeElement;
            return ['INPUT', 'TEXTAREA', 'SELECT'].includes(el?.tagName)
                || el?.isContentEditable
                || !! el?.closest?.('[contenteditable]');
        },
        canHandleListKey() {
            // Same rule as the page shortcuts: a list behind an open modal (or
            // under a stacked one) keeps its listener but must stay quiet.
            return (this.$store.app.currentId == listId)
                && isTopLayer(this.$el)
                && ! this.isInBlockingField();
        },
        onArrow(event, direction) {
            if (! this.canHandleListKey()) return;
            event.preventDefault();
            this.selectedRow += direction;
        },
        onEnter(event) {
            if (! this.canHandleListKey()) return;
            event.preventDefault();
            this.$wire.findListAction(this.selectedRow);
        },
    }));

    // Mirrors the canonical list state (active view + column filters) into the
    // URL so a shared link reproduces the exact view. Livewire's #[Url]
    // bindings only write on updates, never on page load. Never runs for a
    // list inside a modal — that must not rewrite the page URL.
    Alpine.data('noerdListUrlSync', ({ view, filters }) => ({
        init() {
            if (this.$el.closest('#modal') || this.$el.closest('[modal]')) return;

            const url = new URL(window.location.href);

            if (view) {
                url.searchParams.set('view', view);
            }

            [...url.searchParams.keys()]
                .filter((key) => key.startsWith('cf['))
                .forEach((key) => url.searchParams.delete(key));

            Object.entries(filters || {}).forEach(([key, value]) => url.searchParams.set('cf[' + key + ']', value));

            if (url.toString() !== window.location.href) {
                history.replaceState(history.state, '', url.toString());
            }
        },
    }));

    // Toggles a horizontal scroll affordance while the content overflows.
    Alpine.data('noerdScrollShadow', ({ idleClass = 'noerd-scrollbar-idle' } = {}) => ({
        _observer: null,

        init() {
            const sync = () => this.$el.classList.toggle(idleClass, this.$el.scrollWidth <= this.$el.clientWidth);

            sync();

            this._observer = new ResizeObserver(sync);
            this._observer.observe(this.$el);
            Array.from(this.$el.children).forEach((child) => this._observer.observe(child));
        },
        destroy() {
            this._observer?.disconnect();
            this._observer = null;
        },
    }));

    // Drag & drop uploads on top of Livewire's file upload.
    Alpine.data('noerdDropzone', ({ property = 'temporaryFiles' } = {}) => ({
        isDragging: false,

        handleDrop(event) {
            this.isDragging = false;

            const files = event.dataTransfer?.files;

            if (files && files.length > 0) {
                this.$wire.uploadMultiple(property, files);
            }
        },
    }));

    // A value entangled with the component, used by the mail/phone fields to
    // keep their action link in sync while typing.
    Alpine.data('noerdEntangled', ({ value }) => ({
        v: value,
    }));

    // Multi-select of related records: a searchable dropdown writing an id
    // array back to the component.
    Alpine.data('noerdBelongsToMany', ({ options, selectedIds }) => ({
        options,
        selectedIds,
        search: '',
        open: false,
        highlightedIndex: 0,

        get filteredOptions() {
            return Object.entries(this.options).filter(([id, label]) =>
                ! this.selectedIds.includes(parseInt(id))
                && label.toLowerCase().includes(this.search.toLowerCase())
            );
        },
        addItem(id) {
            if (id && ! this.selectedIds.includes(parseInt(id))) {
                this.selectedIds.push(parseInt(id));
                this.search = '';
                this.highlightedIndex = 0;
            }
        },
        removeItem(id) {
            this.selectedIds = this.selectedIds.filter((i) => i !== parseInt(id));
        },
        getLabel(id) {
            return this.options[id] || '';
        },
        selectHighlighted() {
            if (this.filteredOptions.length > 0 && this.highlightedIndex < this.filteredOptions.length) {
                this.addItem(this.filteredOptions[this.highlightedIndex][0]);
            }
        },
        moveUp() {
            if (this.highlightedIndex > 0) {
                this.highlightedIndex--;
            }
        },
        moveDown() {
            if (this.highlightedIndex < this.filteredOptions.length - 1) {
                this.highlightedIndex++;
            }
        },
    }));

    // Rich text editing. The editor holds the HTML and hands it to the
    // component deferred (sent with the next request, e.g. store) instead of
    // one round trip per keystroke; updatedAt only exists to re-evaluate the
    // isActive() bindings of the toolbar.
    Alpine.data('noerdTiptap', ({ field, content, editable }) => ({
        editor: null,
        content,
        linkUrl: '',
        showLinkInput: false,
        updatedAt: Date.now(),

        init() {
            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: {
                            levels: [1, 2, 3],
                        },
                        link: {
                            openOnClick: false,
                        },
                    }),
                ],
                content: this.content,
                editable,
                editorProps: {
                    attributes: {
                        class: 'rich-text focus:outline-none min-h-[150px] p-3 text-base sm:text-sm',
                    },
                },
                onUpdate: ({ editor }) => {
                    // An empty document must be stored as an empty string, not
                    // as "<p></p>" — otherwise required fields pass validation
                    // and lists render a blank paragraph.
                    this.content = editor.isEmpty ? '' : editor.getHTML();
                    this.updatedAt = Date.now();
                    this.$wire.set(field, this.content, false);
                },
                onSelectionUpdate: () => {
                    this.updatedAt = Date.now();
                },
            });

            // The wrapper is wire:ignore, so a value the SERVER changes after
            // mount (reset after save, "load template" actions) has to be
            // pushed into the editor by hand. Skip echoes of our own updates.
            this.$wire.$watch(field, (value) => {
                const next = value ?? '';
                if (next === this.content) {
                    return;
                }
                this.content = next;
                Alpine.raw(this.editor)?.commands.setContent(next, { emitUpdate: false });
            });
        },
        destroy() {
            Alpine.raw(this.editor)?.destroy();
            this.editor = null;
        },
        command() {
            return Alpine.raw(this.editor).chain().focus();
        },
        isActive(type, attrs = {}) {
            return Boolean(this.updatedAt && Alpine.raw(this.editor)?.isActive(type, attrs));
        },
        setLink() {
            if (this.linkUrl) {
                this.command().setLink({ href: this.linkUrl }).run();
            }
            this.linkUrl = '';
            this.showLinkInput = false;
        },
        removeLink() {
            this.command().unsetLink().run();
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
                // A page behind an open modal is still mounted and still
                // listening; only the layer the user is looking at may act.
                if (! isTopLayer(this.$el)) return;

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
