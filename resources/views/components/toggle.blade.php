@use('Illuminate\Support\Str')

{{--
    An on/off switch for a yes/no setting — the replacement of a Yes/No select.

    Bind it like a checkbox (the bound value is a boolean):
        <x-noerd::toggle wire:model.live="settings.enabled" :label="__('…')" :description="__('…')" />

    The label and the hint sit on the left, the switch on the right: one settings row. Inside
    `<x-noerd::toggle-group>` the rows share one card with dividers. The default slot replaces
    the label; the `below` slot holds content that belongs to the row (an input shown while on).
    The group pads its direct children, so a toggle carries no padding of its own.
--}}
@props(['label' => null, 'description' => null, 'disabled' => false, 'id' => null])

@php
    $id ??= 'toggle-' . Str::random(6);
    $hasText = $label !== null || $description !== null || $slot->isNotEmpty();
@endphp

<div @class([$attributes->get('class')])>
    <label for="{{ $id }}" @class([
        'group flex items-center gap-6 select-none',
        'justify-between' => $hasText,
        'cursor-pointer' => ! $disabled,
        'cursor-not-allowed' => $disabled,
    ])>
        @if ($hasText)
            <span class="min-w-0 flex-1">
                @if ($label !== null)
                    <span @class([
                        'block text-sm font-medium leading-6',
                        'text-gray-900' => ! $disabled,
                        'text-gray-400' => $disabled,
                    ])>{{ $label }}</span>
                @endif
                @if ($slot->isNotEmpty())
                    <span @class(['block text-sm font-medium leading-6', 'text-gray-900' => ! $disabled, 'text-gray-400' => $disabled])>{{ $slot }}</span>
                @endif
                @if ($description !== null)
                    <span class="mt-0.5 block text-[13px] leading-5 text-gray-500">{{ $description }}</span>
                @endif
            </span>
        @endif

        <span class="relative inline-flex h-7 w-12 shrink-0 items-center">
            <input
                @if ($disabled) disabled @endif
                id="{{ $id }}"
                type="checkbox"
                role="switch"
                {{ $attributes->whereDoesntStartWith('class') }}
                class="peer sr-only"
            />

            {{-- Track --}}
            <span aria-hidden="true" class="
                absolute inset-0 rounded-full bg-gray-200 shadow-[inset_0_1px_2px_rgba(15,23,42,0.12)] ring-1 ring-inset ring-gray-900/5
                transition-[background-color,box-shadow] duration-300 ease-out
                group-hover:bg-gray-300
                peer-checked:bg-brand-primary peer-checked:shadow-[inset_0_1px_2px_rgba(0,0,0,0.25)] peer-checked:ring-transparent
                group-hover:peer-checked:bg-brand-primary/90
                peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-primary
                peer-disabled:opacity-40 group-hover:peer-disabled:bg-gray-200 group-hover:peer-disabled:peer-checked:bg-brand-primary
            "></span>

            {{-- Knob: springs across, stretches while pressed (iOS-style), shows a check while on --}}
            <span aria-hidden="true" class="
                pointer-events-none absolute top-1 left-1 flex h-5 w-5 items-center justify-center rounded-full bg-white
                shadow-[0_1px_2px_rgba(15,23,42,0.2),0_2px_6px_rgba(15,23,42,0.12)]
                transition-[translate,width] duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]
                group-active:w-6
                peer-checked:translate-x-5 group-active:peer-checked:translate-x-4
                peer-disabled:shadow-none group-active:peer-disabled:w-5 group-active:peer-disabled:peer-checked:translate-x-5
            ">
                <svg viewBox="0 0 12 12" fill="none"
                     class="h-3 w-3 scale-50 text-brand-primary opacity-0 transition duration-200 group-has-checked:scale-100 group-has-checked:opacity-100">
                    <path d="M3 6.25 5.1 8.3 9 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </span>
    </label>

    @isset($below)
        <div class="mt-4">{{ $below }}</div>
    @endisset
</div>
