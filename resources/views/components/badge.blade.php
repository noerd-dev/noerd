{{-- The one pill of the framework: a badge cell, a relation title, the badge of a
     badge_with_text cell, a status chip in a module view. `variant` colours it
     (neutral by default, primary, success, warning, danger), `size` xs is the
     widget-list size. --}}
@props([
    'variant' => 'neutral',
    'size' => 'sm',
])

@php
    $badgeVariantClasses = match ($variant) {
        'primary' => 'bg-brand-primary/10 text-brand-primary',
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-yellow-100 text-yellow-800',
        'danger' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2 py-0.5 font-medium whitespace-nowrap',
        $badgeVariantClasses,
        'text-[11px]' => $size === 'xs',
        'text-xs leading-tight' => $size !== 'xs',
    ]) }}
>{{ $slot }}</span>
