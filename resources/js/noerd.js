import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import sort from '@alpinejs/sort';
import focus from '@alpinejs/focus';
import { noerdUploadQueue } from './upload-chunks.js';
import { clampRow, isEditableElement, matchesShortcut, parseShortcut } from './keyboard.js';
import { editableCurrency, formatCurrency, parseCurrency } from './currency.js';
import { hasId, noerdLinkHref, normalizeId, withoutId } from './form-values.js';
import { extendAppStore } from './app-store.js';

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

document.addEventListener('alpine:init', () => {
    // Alpine Sort Plugin
    Alpine.plugin(sort);
    Alpine.plugin(focus);

    // The core's part of the shared store `app`: the list that owns the
    // keyboard navigation (noerdList). noerd/modal adds its modal flags to the
    // same store — see app-store.js for why neither side replaces it.
    extendAppStore(Alpine, {
        currentId: null,
        setId(id) {
            this.currentId = id;
        },
    });

    // Currency input: the bound value stays a plain number — or null. The
    // field shows it formatted with the tenant's separators and accepts either
    // notation. An empty input means "no amount", never 0, so an optional
    // amount column stays NULL and a form never shows a value it does not hold.
    //
    // The parsing rules live in currency.js. An input that cannot be read
    // without guessing ("1,234.56" in a comma locale) is rejected: the bound
    // value stays as it was and the input is marked aria-invalid until the
    // reader corrects it.
    Alpine.data('noerdCurrency', ({ name, decSep, thousSep }) => ({
        rawValue: null,
        invalid: false,
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
            return formatCurrency(val, decSep, thousSep);
        },
        showFormatted() {
            this.$refs.input.value = this.formatDisplay(this.rawValue);
        },
        markInvalid(invalid) {
            this.invalid = invalid;
            if (invalid) {
                this.$refs.input.setAttribute('aria-invalid', 'true');
            } else {
                this.$refs.input.removeAttribute('aria-invalid');
            }
        },
        onFocus(e) {
            // A rejected input stays as the reader typed it, so it can be fixed.
            if (! this.invalid) {
                e.target.value = editableCurrency(this.rawValue, decSep);
            }
            this.$nextTick(() => e.target.select());
        },
        onBlur(e) {
            const parsed = parseCurrency(e.target.value, decSep);

            if (! parsed.valid) {
                this.markInvalid(true);
                return;
            }

            this.markInvalid(false);

            // Only a changed amount is written: focusing and leaving the field
            // must neither round the value nor mark the form dirty.
            if (parsed.value !== this.rawValue) {
                this.rawValue = parsed.value;
                this.$wire.set(name, parsed.value);
            }

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

    // The application shell: sidebar/app-bar visibility with a desktop
    // breakpoint that remembers the desktop layout across mobile detours.
    Alpine.data('noerdAppShell', ({ showSidebar, showAppbar }) => ({
        isModal: false,
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
    // The row count is read from `data-row-count` on every key press: a search
    // or a page change morphs the attribute, the x-data arguments stay as they
    // were on the first render.
    Alpine.data('noerdList', ({ listId, rowCount = 0 }) => ({
        selectedRow: 0,

        init() {
            this.$store.app.setId(listId);
        },
        claim() {
            this.$store.app.setId(listId);
        },
        isInBlockingField() {
            return isEditableElement(document.activeElement);
        },
        rowCount() {
            const live = parseInt(this.$root.dataset.rowCount ?? '', 10);

            return Number.isNaN(live) ? rowCount : live;
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
            this.selectedRow = clampRow(this.selectedRow, direction, this.rowCount());
        },
        onEnter(event) {
            if (! this.canHandleListKey() || this.rowCount() === 0) return;
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
            if (this.$el.closest('[modal]')) return;

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
    //
    // A selection is uploaded in CONSECUTIVE requests: the browser hands
    // everything to Livewire in one POST, and PHP refuses a request carrying
    // more than `max_file_uploads` files or more than `post_max_size` bytes —
    // by answering with a warning instead of a response, so the whole upload
    // fails with nothing to see. Splitting the selection server-side is not
    // possible; the request never arrives. See noerdChunkUploads().
    //
    // A selection dropped while an upload runs is queued (noerdUploadQueue),
    // and a failed or cancelled batch always ends the run, so the dropzone
    // never stays locked. The queue lives in this closure and not on the
    // reactive object: a File behind an Alpine proxy is no Blob for FormData.
    Alpine.data('noerdDropzone', ({ property = 'temporaryFiles', maxFiles = 20, maxBytes = 0 } = {}) => {
        let queue = null;

        return {
        isDragging: false,
        uploading: false,
        uploadedCount: 0,
        totalCount: 0,
        uploadError: '',
        // What one request takes — the view shows a counter above it.
        chunkSize: maxFiles,

        init() {
            queue = noerdUploadQueue({
                maxFiles,
                maxBytes,
                send: (batch) => this.uploadChunk(batch),
                onChange: (state) => {
                    this.uploading = state.uploading;
                    this.uploadedCount = state.uploadedCount;
                    this.totalCount = state.totalCount;
                    this.uploadError = state.error;
                },
            });
        },

        handleDrop(event) {
            this.isDragging = false;
            this.upload(event.dataTransfer?.files);
        },

        handleSelect(event) {
            this.upload(event.target.files);
            // Free the input so selecting the same files again still fires.
            event.target.value = '';
        },

        upload(fileList) {
            return queue.add(Array.from(fileList || []));
        },

        uploadChunk(chunk) {
            // Livewire calls the error callback without any detail (no status,
            // no response), so there is ONE message for every failure. A
            // cancelled upload rejects as well, without a message.
            return new Promise((resolve, reject) => {
                this.$wire.uploadMultiple(
                    property,
                    chunk,
                    () => resolve(),
                    () => reject(this.$root.dataset.uploadFailed ?? ''),
                    () => {},
                    () => reject(''),
                );
            });
        },
        };
    });

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
                ! hasId(this.selectedIds, id)
                && String(label).toLowerCase().includes(this.search.toLowerCase())
            );
        },
        addItem(id) {
            if (id !== null && id !== undefined && id !== '' && ! hasId(this.selectedIds, id)) {
                this.selectedIds = [...(this.selectedIds || []), normalizeId(id)];
                this.search = '';
                this.highlightedIndex = 0;
            }
        },
        removeItem(id) {
            this.selectedIds = withoutId(this.selectedIds, id);
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
                            defaultProtocol: 'https',
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
            const href = noerdLinkHref(this.linkUrl);
            if (href) {
                this.command().setLink({ href }).run();
            }
            this.linkUrl = '';
            this.showLinkInput = false;
        },
        removeLink() {
            this.command().unsetLink().run();
        },
    }));

    // The dropdown menu of x-noerd::action-menu (WAI-ARIA menu button): opening
    // focuses the first entry, Arrow keys / Home / End move between entries,
    // Escape and every close hand the focus back to the element that opened
    // the menu. `open` stays a plain property, so custom triggers keep
    // toggling it with `open = ! open`.
    Alpine.data('noerdActionMenu', () => ({
        open: false,
        _returnFocus: null,

        init() {
            this.$watch('open', (open) => {
                if (open) {
                    const active = document.activeElement;
                    this._returnFocus = active && this.$root.contains(active) ? active : null;
                    this.$nextTick(() => this.focusItem(0));

                    return;
                }

                const target = this._returnFocus;
                this._returnFocus = null;

                // Only take the focus back when it is still inside the menu —
                // a click elsewhere keeps what the reader clicked.
                if (target && (this.$root.contains(document.activeElement) || document.activeElement === document.body)) {
                    target.focus?.();
                }
            });
        },
        items() {
            return Array.from(this.$root.querySelectorAll('[role="menuitem"]'))
                .filter((item) => item.closest('[data-noerd-action-menu]') === this.$root
                    && ! item.disabled
                    && item.offsetParent !== null);
        },
        focusItem(index) {
            const items = this.items();

            if (items.length === 0) return;

            items[(index + items.length) % items.length].focus();
        },
        onKeydown(event) {
            if (! this.open) {
                if (['ArrowDown', 'ArrowUp'].includes(event.key) && event.target.closest('[role="menu"]') === null) {
                    event.preventDefault();
                    this.open = true;
                }

                return;
            }

            const items = this.items();
            const current = items.indexOf(document.activeElement);

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.focusItem(current + 1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.focusItem(current === -1 ? items.length - 1 : current - 1);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.focusItem(0);
                    break;
                case 'End':
                    event.preventDefault();
                    this.focusItem(items.length - 1);
                    break;
                case 'Tab':
                    this.open = false;
                    break;
            }
        },
    }));

    // Keyboard shortcut support for detail pages
    Alpine.data('noerdPage', ({ currentTab, shortcuts, deleteMessage }) => ({
        currentTab,
        // Both shadow the inherited scope for this page and everything inside it.
        // The modal panel sets `isModal` for its whole subtree, so a full page nested
        // in another page (e.g. a list embedded in a detail tab) used to take the
        // modal chrome too: negative margins that overflow the tab panel, the modal
        // max-height and the header space reserved for the panel controls.
        isModal: false,
        isNestedPage: false,
        _parsedShortcuts: {},
        _keydownHandler: null,

        init() {
            // Runs before the root's :class binding (x-data initialises first).
            const parent = this.$el.parentElement;
            this.isNestedPage = Boolean(parent?.closest('[data-noerd-page]'));
            this.isModal = ! this.isNestedPage && Boolean(parent && Alpine.$data(parent)?.isModal);

            for (const [action, str] of Object.entries(shortcuts || {})) {
                this._parsedShortcuts[action] = parseShortcut(str);
            }

            this._keydownHandler = (e) => {
                // A page behind an open modal is still mounted and still
                // listening; only the layer the user is looking at may act.
                if (! isTopLayer(this.$el)) return;

                if ('save' in this._parsedShortcuts && matchesShortcut(e, this._parsedShortcuts.save)) {
                    e.preventDefault();
                    this.commitFocusedField();
                    this.$wire.store();
                    return;
                }
                // Ctrl+Backspace deletes a word in a text field — it must never
                // delete the record the reader is typing into.
                if ('delete' in this._parsedShortcuts
                    && ! isEditableElement(document.activeElement)
                    && matchesShortcut(e, this._parsedShortcuts.delete)) {
                    e.preventDefault();
                    if (window.confirm(deleteMessage)) {
                        this.$wire.delete();
                    }
                }
            };

            window.addEventListener('keydown', this._keydownHandler);
        },

        // Fields that write on blur (the currency field, wire:model.blur) still
        // hold the typed value only in the DOM when the save shortcut fires.
        // Blurring first queues their update ahead of store(), in the same
        // request queue, so the record is saved with what is on screen.
        commitFocusedField() {
            const active = document.activeElement;

            if (active && active !== document.body && this.$el.contains(active) && typeof active.blur === 'function') {
                active.blur();
            }
        },

        destroy() {
            if (this._keydownHandler) {
                window.removeEventListener('keydown', this._keydownHandler);
                this._keydownHandler = null;
            }
        },
    }));
});
