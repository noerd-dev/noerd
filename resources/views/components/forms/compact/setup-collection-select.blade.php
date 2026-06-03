{{-- Compact variant of forms.setup-collection-select: label sits to the LEFT of the select. --}}
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
    use Noerd\Models\SetupCollection;
    use Noerd\Helpers\SetupCollectionHelper;

    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $collectionKey = $field['collectionKey'] ?? $collectionKey;
    $displayField = $field['displayField'] ?? $displayField;
    $valueField = $field['valueField'] ?? $valueField;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;

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

<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>
    <div class="flex-1 min-w-0">
        <select
            @if($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
            class="w-full border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-0.5 h-7 ps-2 pe-2 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
            id="{{ $name }}"
        >
            @foreach($options as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
    </div>
</div>
