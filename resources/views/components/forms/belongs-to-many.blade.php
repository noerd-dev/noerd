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

    $options = $this->callAMethod([$this, $optionsMethod]);
    $selectedIds = $this->$name ?? [];
@endphp

<div x-data="{
    search: '',
    open: false,
    highlightedIndex: 0,
    selectedIds: @entangle($name),
    options: {{ json_encode($options) }},
    get filteredOptions() {
        return Object.entries(this.options).filter(([id, label]) =>
            !this.selectedIds.includes(parseInt(id)) &&
            label.toLowerCase().includes(this.search.toLowerCase())
        );
    },
    addItem(id) {
        if (id && !this.selectedIds.includes(parseInt(id))) {
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
}" @click.outside="open = false">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>

    <div class="flex flex-wrap gap-2 mb-2 min-h-[2rem]">
        <template x-for="id in selectedIds" :key="id">
            <span class="inline-flex items-center gap-1 px-2 py-1 text-sm bg-zinc-100 text-zinc-700 rounded-md border border-zinc-200">
                <span x-text="getLabel(id)"></span>
                <button type="button" @click="removeItem(id)" class="text-zinc-400 hover:text-zinc-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        </template>
        <span x-show="selectedIds.length === 0" class="text-zinc-400 text-sm py-1">
            {{ __('Keine Auswahl') }}
        </span>
    </div>

    <div class="relative">
        <input
            type="text"
            x-model="search"
            @focus="open = true; highlightedIndex = 0"
            @keydown.enter.prevent="selectHighlighted()"
            @keydown.arrow-up.prevent="moveUp()"
            @keydown.arrow-down.prevent="moveDown()"
            @keydown.escape="open = false"
            placeholder="{{ __('Suchen und hinzufügen...') }}"
            class="w-full border rounded-lg block disabled:shadow-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
        />

        <div
            x-show="open && filteredOptions.length > 0"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-1 w-full max-h-60 overflow-auto rounded-lg bg-white border border-zinc-200 shadow-lg"
        >
            <template x-for="([id, label], index) in filteredOptions" :key="id">
                <button
                    type="button"
                    @click="addItem(id)"
                    @mouseenter="highlightedIndex = index"
                    :class="highlightedIndex === index ? 'bg-zinc-100' : ''"
                    class="w-full px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                    x-text="label"
                ></button>
            </template>
        </div>

        <div x-show="open && search.length > 0 && filteredOptions.length === 0" class="absolute z-50 mt-1 w-full rounded-lg bg-white border border-zinc-200 shadow-lg px-3 py-2 text-sm text-zinc-500">
            {{ __('Keine Ergebnisse gefunden') }}
        </div>
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
