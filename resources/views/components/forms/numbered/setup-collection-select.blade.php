{{-- Numbered variant of forms.setup-collection-select: gray numbered row, right-aligned label, select on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'collectionKey' => '',
    'displayField' => 'name',
    'valueField' => null,
    'live' => false,
])

@php
    use Noerd\Helpers\SetupCollectionHelper;
    use Noerd\Models\SetupCollection;

    $name = $field['name'] ?? $name;
    $collectionKey = $field['collectionKey'] ?? $collectionKey;
    $displayField = $field['displayField'] ?? $displayField;
    $valueField = $field['valueField'] ?? $valueField;
    $live = $field['live'] ?? $live;

    $locale = session('selectedLanguage') ?? 'de';

    $collection = SetupCollection::where('collection_key', $collectionKey)->first();
    $entries = $collection?->entries ?? collect();

    $collectionConfig = SetupCollectionHelper::getCollectionFields(mb_strtolower($collectionKey));
    $fieldConfig = collect($collectionConfig['fields'] ?? [])->firstWhere('name', 'detailData.' . $displayField);
    $isTranslatable = in_array($fieldConfig['type'] ?? '', ['translatableText', 'translatableTextarea']);

    $options = [['value' => '', 'label' => 'Bitte wählen']];
    foreach ($entries as $entry) {
        $optionLabel = $entry->data[$displayField] ?? '';
        if (is_array($optionLabel)) {
            $optionLabel = $optionLabel[$locale] ?? $optionLabel['de'] ?? reset($optionLabel) ?? '';
        }
        $optionValue = $valueField ? ($entry->data[$valueField] ?? '') : $entry->id;
        $options[] = ['value' => $optionValue, 'label' => $optionLabel];
    }
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="block h-9 w-full appearance-none rounded-none border border-zinc-400 bg-white py-1 ps-2 pe-2 text-base sm:text-sm text-zinc-700 placeholder-zinc-400 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70"
        id="{{ $name }}"
    >
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
</x-noerd::detail.numbered-row>
