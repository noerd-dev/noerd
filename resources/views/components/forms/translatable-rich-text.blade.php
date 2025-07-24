<div wire:key="{{($field['name'] ?? $name) . (session('selectedLanguage') ?? 'de')}}"  {{ isset($attributes) ?$attributes->merge(['class' => '']) : '' }}>
    <x-noerd::input-label for="{{$field['name'] ?? $name}}" :value="$field['label'] ?? $label"/>

    <x-noerd::forms.quill :field="$field['name'].'.'.(session('selectedLanguage') ?? 'de')"
                   :content="$model[str_replace('model.', '',$field['name'] )][session('selectedLanguage')] ?? ''"
    />

    <x-noerd::input-error :messages="$errors->get($field['name'] ?? $name)" class="mt-2"/>
</div>
