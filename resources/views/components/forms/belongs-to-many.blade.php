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
    selectedIds: @entangle($name),
    options: {{ json_encode($options) }},
    get availableOptions() {
        return Object.entries(this.options).filter(([id]) => !this.selectedIds.includes(parseInt(id)));
    },
    addItem(id) {
        if (id && !this.selectedIds.includes(parseInt(id))) {
            this.selectedIds.push(parseInt(id));
        }
    },
    removeItem(id) {
        this.selectedIds = this.selectedIds.filter(i => i !== parseInt(id));
    },
    getLabel(id) {
        return this.options[id] || '';
    }
}">
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

    <select
        x-show="availableOptions.length > 0"
        @change="addItem($event.target.value); $event.target.value = ''"
        class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
    >
        <option value="">{{ __('Hinzufügen...') }}</option>
        <template x-for="[id, label] in availableOptions" :key="id">
            <option :value="id" x-text="label"></option>
        </template>
    </select>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
