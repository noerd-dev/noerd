@props(['disabled' => false, 'startField' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'mt-1 disabled:opacity-50 block w-full !py-1.5 px-3 rounded-xs border border-gray-300 shadow-xs focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 sm:text-sm']) !!}>
