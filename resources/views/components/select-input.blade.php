@props(['disabled' => false, 'startField' => false])

<select {{ $disabled ? 'disabled' : '' }} {{ $startField ? "id=component-start-field" : '' }} {!! $attributes->merge(['class' => 'mt-1 disabled:opacity-50 block w-full py-1.5 border px-3 rounded-sm border-gray-300 shadow-xs focus:border-brand-border focus:ring-brand-border sm:text-sm']) !!}>
    {{$slot}}
</select>
