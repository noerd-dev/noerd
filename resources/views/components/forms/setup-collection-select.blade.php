@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'collectionKey' => '',
    'displayField' => 'name',
    'live' => false,
    'required' => false,
])

@php
    use Noerd\Noerd\Models\SetupCollection;
    use Noerd\Noerd\Helpers\SetupCollectionHelper;

    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $collectionKey = $field['collectionKey'] ?? $collectionKey;
    $displayField = $field['displayField'] ?? $displayField;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;

    // Get current locale
    $locale = session('selectedLanguage') ?? 'de';

    // Get collection entries
    $collection = SetupCollection::where('collection_key', $collectionKey)->first();
    $entries = $collection?->entries ?? collect();

    // Get collection config to check if displayField is translatable
    $collectionConfig = SetupCollectionHelper::getCollectionFields(mb_strtolower($collectionKey));
    $fieldConfig = collect($collectionConfig['fields'] ?? [])->firstWhere('name', 'model.' . $displayField);
    $isTranslatable = in_array($fieldConfig['type'] ?? '', ['translatableText', 'translatableTextarea']);

    // Build options array
    $options = [['value' => '', 'label' => 'Bitte wählen']];
    foreach ($entries as $entry) {
        $value = $entry->data[$displayField] ?? '';
        if ($isTranslatable && is_array($value)) {
            $value = $value[$locale] ?? $value['de'] ?? reset($value) ?? '';
        }
        $options[] = ['value' => $entry->id, 'label' => $value];
    }
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>
    <select
        @if($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="w-full border rounded-lg block disabled:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
        id="{{ $name }}"
    >
        @foreach($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
