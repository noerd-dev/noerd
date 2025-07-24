@props(['disabled' => false, 'startField' => false])

<select {{ $disabled ? 'disabled' : '' }} {{ $startField ? "id=component-start-field" : '' }} {!! $attributes->merge(['class' => 'mt-1 disabled:opacity-50 block w-full py-1.5 border px-3 rounded-xs border-gray-300 shadow-xs focus:border-black focus:ring-black sm:text-sm']) !!}>
    {{$slot}}
</select>
