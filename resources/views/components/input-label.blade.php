{{-- `helpText` comes from the YAML field key of the same name. It is normally read
     from FieldContext (set per field by noerd::components.detail.block), so element
     templates need not forward it; pass the prop explicitly where there is no block
     context, e.g. the relation-field Livewire views. The same applies to
     `translatable`, which marks a field holding one value per language, and to
     `highlighted`, which marks a prefilled value the reader did not enter. --}}
@props(['value' => null, 'required' => false, 'helpText' => null, 'translatable' => null, 'highlighted' => null])

@php
    $resolvedHelpText = $helpText ?: \Noerd\Support\FieldContext::helpText();
    $resolvedTranslatable = $translatable ?? \Noerd\Support\FieldContext::isTranslatable();
    $resolvedHighlighted = $highlighted ?? \Noerd\Support\FieldContext::isHighlighted();
@endphp

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm leading-6 text-gray-700 pb-2']) }}>
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
    @if ($resolvedHighlighted)
        <x-noerd::help-tooltip
            icon="sparkles"
            iconClass="text-amber-500 hover:text-amber-600"
            :text="__('This value was proposed for you. Check it before saving.')"
        />
    @endif
</label>
