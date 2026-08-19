{{-- Numbered variant of forms.setup-collection-select: gray numbered row, right-aligned label, select on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'collectionKey' => '',
    'displayField' => 'name',
    'valueField' => null,
    'live' => false,
    'readonly' => false,
])

@php
    use Noerd\Helpers\SetupCollectionHelper;

    $name = $field['name'] ?? $name;
    $collectionKey = $field['collectionKey'] ?? $collectionKey;
    $displayField = $field['displayField'] ?? $displayField;
    $valueField = $field['valueField'] ?? $valueField;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;

    // Build options array — shared resolution with the list picklist badges.
    $options = [
        ['value' => '', 'label' => 'Bitte wählen'],
        ...SetupCollectionHelper::selectOptions($collectionKey, $displayField, $valueField),
    ];
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="block h-9 w-full appearance-none rounded-none border border-zinc-400 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70 sm:text-sm"
        @if ($readonly) disabled @endif
        id="{{ $name }}"
    >
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
</x-noerd::detail.numbered-row>
