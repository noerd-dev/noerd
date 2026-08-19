{{-- `helpText` comes from the YAML field key of the same name. It is normally read
     from FieldContext (set per field by noerd::components.detail.block), so element
     templates need not forward it; pass the prop explicitly where there is no block
     context, e.g. the relation-field Livewire views. The same applies to
     `translatable`, which marks a field holding one value per language. --}}
@props(['value' => null, 'required' => false, 'helpText' => null, 'translatable' => null])

@php
    $resolvedHelpText = $helpText ?: \Noerd\Support\FieldContext::helpText();
    $resolvedTranslatable = $translatable ?? \Noerd\Support\FieldContext::isTranslatable();
@endphp

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm pb-0 leading-6 text-gray-700 pb-2']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-red-500">*</span>
    @endif
    @if ($resolvedHelpText)
        <x-noerd::help-tooltip :text="__($resolvedHelpText)" />
    @endif
    @if ($resolvedTranslatable)
        <x-noerd::help-tooltip
            icon="language"
            iconClass="text-sky-500 hover:text-sky-700"
            :text="__('This field is translatable. The value belongs to the language selected in the language switcher.')"
        />
    @endif
</label>
