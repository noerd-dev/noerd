@use('Illuminate\Support\Str')

@props(['disabled' => false, 'id' => null])

@php
    $id ??= 'checkbox-' . Str::random(6);
@endphp

<div class="relative my-auto flex items-start">
    <div class="flex h-6 items-center">
        <input
            @if ($disabled) disabled @endif
            id="{{ $id }}"
            {{ $attributes->whereDoesntStartWith('class') }}
            type="checkbox"
            class="text-brand-primary focus:ring-brand-border h-4 w-4 cursor-pointer rounded-sm border border-gray-400"
        />
    </div>
    <div class="ml-3 text-sm leading-6">
        <label for="{{ $id }}" class="cursor-pointer font-medium text-gray-900">{{ $slot }}</label>
    </div>
</div>
