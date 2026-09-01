{{-- Shared <option> list for every theme's input-select: a select must never
     display a value the component does not hold. Without a matching option the
     browser falls back to showing the first one, so a null value silently reads
     as "the first option is selected" and is then lost on save. --}}
@php
    $selectedValue = data_get($this, $name);
    $isEmptySelection = $selectedValue === null || $selectedValue === '';

    $optionValues = [];
    foreach ($options as $option) {
        $optionValues[] = (string) (is_array($option) ? ($option['value'] ?? '') : $option);
    }

    $hasUnlistedValue = !$isEmptySelection && !in_array((string) $selectedValue, $optionValues, true);
@endphp

@if ($isEmptySelection || $placeholder)
    <option value="">{{ $placeholder ? __($placeholder) : '' }}</option>
@endif

@if ($hasUnlistedValue)
    <option value="{{ $selectedValue }}">{{ $selectedValue }}</option>
@endif

@foreach ($options as $option)
    @isset($option['value'])
        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
    @else
        <option>{{ __($option) }}</option>
    @endisset
@endforeach
