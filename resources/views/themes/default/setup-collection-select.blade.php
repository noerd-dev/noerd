@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'collectionKey' => '',
    'displayField' => 'name',
    'valueField' => null,
    'live' => false,
    'required' => false,
])

@php
    use Noerd\Helpers\SetupCollectionHelper;
    use Noerd\Models\SetupCollection;

    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $collectionKey = $field['collectionKey'] ?? $collectionKey;
    $displayField = $field['displayField'] ?? $displayField;
    $valueField = $field['valueField'] ?? $valueField;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;

    // Get current locale
    $locale = session('selectedLanguage') ?? 'de';

    // Get collection entries
    $collection = SetupCollection::where('collection_key', $collectionKey)->first();
    $entries = $collection?->entries ?? collect();

    // Get collection config to check if displayField is translatable
    $collectionConfig = SetupCollectionHelper::getCollectionFields(mb_strtolower($collectionKey));
    $fieldConfig = collect($collectionConfig['fields'] ?? [])->firstWhere('name', 'detailData.' . $displayField);
    $isTranslatable = in_array($fieldConfig['type'] ?? '', ['translatableText', 'translatableTextarea']);

    // Build options array
    $options = [['value' => '', 'label' => 'Bitte wählen']];
    foreach ($entries as $entry) {
        $optionLabel = $entry->data[$displayField] ?? '';
        // Always handle array values (translatable fields) - get locale, fallback to 'de', then any available
        if (is_array($optionLabel)) {
            $optionLabel = $optionLabel[$locale] ?? $optionLabel['de'] ?? reset($optionLabel) ?? '';
        }
        $optionValue = $valueField ? ($entry->data[$valueField] ?? '') : $entry->id;
        $options[] = ['value' => $optionValue, 'label' => $optionLabel];
    }
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="focus:ring-brand-border block h-8 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:border-b-zinc-200 disabled:text-zinc-500 disabled:placeholder-zinc-400/70 disabled:shadow-none sm:text-sm"
        id="{{ $name }}"
    >
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
