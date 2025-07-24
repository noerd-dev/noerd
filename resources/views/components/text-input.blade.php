@props(['disabled' => false, 'startField' => false])

<input {{ $disabled ? 'disabled' : '' }} {{ $startField ? "id=component-start-field2" : '' }} {!! $attributes->merge(['class' => 'mt-1 disabled:opacity-50 block w-full !py-1.5 px-3 rounded-xs border border-gray-300 shadow-xs focus:border-black focus:ring-black sm:text-sm']) !!}>
