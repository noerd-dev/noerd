@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;

    $collections = \Noerd\Cms\Models\Collection::where('tenant_id', auth()->user()->selected_tenant_id)->orderBy('name')->get();
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>
    <div class="flex">
        <select
            @if($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
            {{ $readonly ? 'disabled' : '' }}
            class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            id="{{ $name }}"
        >
            <option value="">{{ __('noerd_please_select') }}</option>
            @foreach($collections as $collection)
                <option value="{{ $collection->id }}">{{ $collection->name }}</option>
            @endforeach
        </select>

        <x-noerd::buttons.primary
            x-data="{ collectionKey: $wire.entangle('{{ $name }}') }"
            @click="$dispatch('noerdModal', {component: 'collection-entries-list', arguments: {collectionKey: collectionKey, context: '{{ $name }}'}})"
            class="h-9rounded !mt-0 !ml-1"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::buttons.primary>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
