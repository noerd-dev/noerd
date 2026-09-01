@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'optionsMethod' => '',
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $optionsMethod = $field['optionsMethod'] ?? $optionsMethod;
    $required = $field['required'] ?? $required;
    $readonly = $field['readonly'] ?? false;

    $options = $optionsMethod && method_exists($this, $optionsMethod) ? $this->{$optionsMethod}() : [];
    $selectedIds = $this->{$name} ?? [];
@endphp

<div
    x-data="{
    search: '',
    open: false,
    highlightedIndex: 0,
    selectedIds: @entangle($name),
    options: {{ json_encode($options) }},
    get filteredOptions() {
        return Object.entries(this.options).filter(([id, label]) =>
            ! this.selectedIds.includes(parseInt(id)) &&
            label.toLowerCase().includes(this.search.toLowerCase())
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
        this.selectedIds = this.selectedIds.filter(i => i !== parseInt(id));
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
    }
}"
    @click.outside="open = false"
>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <div class="mb-2 flex min-h-[2rem] flex-wrap gap-2">
        <template x-for="id in selectedIds" :key="id">
            <span class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-zinc-100 px-2 py-1 text-sm text-zinc-700">
                <span x-text="getLabel(id)"></span>
                @unless ($readonly)
                    <x-noerd::button variant="icon" icon="x-mark" type="button" @click="removeItem(id)" />
                @endunless
            </span>
        </template>
        <span x-show="selectedIds.length === 0" class="py-1 text-sm text-zinc-400"> {{ __('No selection') }} </span>
    </div>

    @unless ($readonly)
        <div class="relative">
            <input
                type="text"
                x-model="search"
                @focus="
                    open = true;
                    highlightedIndex = 0;
                "
                @keydown.enter.prevent="selectHighlighted()"
                @keydown.arrow-up.prevent="moveUp()"
                @keydown.arrow-down.prevent="moveDown()"
                @keydown.escape="open = false"
                placeholder="{{ __('Search and add…') }}"
                class="focus:ring-brand-border block h-10 w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:border-b-zinc-200 disabled:text-zinc-500 disabled:placeholder-zinc-400/70 disabled:shadow-none sm:text-sm"
            />

            <div
                x-show="open && filteredOptions.length > 0"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white shadow-lg"
            >
                <template x-for="([id, label], index) in filteredOptions" :key="id">
                    <button
                        type="button"
                        @click="addItem(id)"
                        @mouseenter="highlightedIndex = index"
                        :class="highlightedIndex === index ? 'bg-zinc-100' : ''"
                        class="w-full cursor-pointer px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100"
                        x-text="label"
                    ></button>
                </template>
            </div>

            <div
                x-show="open && search.length > 0 && filteredOptions.length === 0"
                class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-500 shadow-lg"
            >
                {{ __('No results found') }}
            </div>
        </div>
    @endunless

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
