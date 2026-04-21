@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'wireTarget' => null,
    'type' => null,
])

@php
    if ($icon === null && $variant === 'danger') {
        $icon = 'trash';
    }

    $isIconOnly = $variant === 'icon';

    $type = $type ?? ($variant === 'primary' ? 'submit' : 'button');

    $baseClasses = 'my-auto inline-flex cursor-pointer items-center justify-center gap-2 transition focus:outline-hidden focus:ring-2 focus:ring-offset-2 disabled:opacity-25'
        . ($variant === 'pill' ? ' rounded-lg' : ' rounded-sm');

    $sizeClasses = match ($size) {
        'sm' => $isIconOnly ? 'h-6 w-6' : 'h-6 px-2.5 py-1 text-xs',
        'lg' => $isIconOnly ? 'h-10 w-10' : 'h-10 px-5 py-2.5 text-base',
        default => $isIconOnly ? 'h-8 w-8' : 'h-8 px-4 py-1.5 text-sm',
    };

    $variantClasses = match ($variant) {
        'primary' => '!bg-brand-primary text-brand-primary-text shadow-xs hover:bg-brand-primary/80 focus:ring-brand-border',
        'secondary' => 'border border-gray-300 !bg-brand-secondary text-brand-secondary-text shadow-xs hover:bg-brand-secondary/80 focus:ring-brand-primary/80',
        'danger' => '!bg-brand-danger text-brand-danger-text border border-gray-300 shadow-xs hover:bg-brand-danger/80 focus:ring-red-500',
        'pill' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-brand-primary/80',
        'ghost', 'icon' => 'text-gray-700 hover:bg-gray-100 focus:ring-brand-primary/80',
        default => '',
    };

    $iconSize = match ($size) {
        'sm' => 'w-4 h-4',
        default => 'w-5 h-5',
    };

    $iconComponent = $icon
        ? (str_contains($icon, '::') ? $icon : 'heroicons::outline.' . $icon)
        : null;
@endphp

<button type="{{ $type }}"
    {{ $attributes->merge(['class' => trim($baseClasses . ' ' . $sizeClasses . ' ' . $variantClasses)]) }}>

    @if ($wireTarget)
        <svg wire:loading wire:target="{{ $wireTarget }}"
             class="animate-spin {{ $iconSize }}"
             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @endif

    @if ($iconComponent)
        @if ($wireTarget)
            <span wire:loading.remove wire:target="{{ $wireTarget }}" class="inline-flex">
                <x-dynamic-component :component="$iconComponent" class="{{ $iconSize }}"/>
            </span>
        @else
            <x-dynamic-component :component="$iconComponent" class="{{ $iconSize }}"/>
        @endif
    @endif

    {{ $slot }}
</button>
