<div  {{ isset($attributes) ?$attributes->merge(['class' => '']) : '' }}>
    <x-noerd::input-label for="{{$field['name'] ?? $name}}" :value="$field['label'] ?? $label"/>

    <x-noerd::forms.quill :field="$field['name']" :content="$model[str_replace('model.', '',$field['name'] )] ?? ''"/>

    <x-noerd::input-error :messages="$errors->get($field['name'] ?? $name)" class="mt-2"/>
</div>
