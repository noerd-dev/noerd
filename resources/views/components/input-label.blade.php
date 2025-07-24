@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm pb-0 leading-6 text-gray-700 pb-2']) }}>
    {{ $value ?? $slot }}
</label>
