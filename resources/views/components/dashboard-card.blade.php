@props([
    'title' => '',
    'route' => null,
    'component' => null,
    'rewriteUrl' => true,
    'arguments' => [],
    'external' => null,
    'icon' => null,
    'heroicon' => null,
    'value' => null,
    'background' => null,
])

@php
    // route wins when registered, component is the fallback. rewriteUrl: false for
    // cards that open a FILTERED list — a reload of the plain list route would show
    // the unfiltered list.
    $cardRoute = ($route ?? null);
    $cardRoute = $cardRoute && \Illuminate\Support\Facades\Route::has($cardRoute) ? $cardRoute : null;
    $cardComponent = $component ?? null;
    $cardOptions = array_filter([
        'fallbackComponent' => $cardComponent,
        'rewriteUrl' => ($rewriteUrl ?? true) ? null : false,
    ], fn ($value): bool => $value !== null);
@endphp

<a
    @isset($external) href="{{ $external }}" target="_blank" @else href="#/" @endisset
    @if ($cardRoute)
        @click="$modalRoute({{ \Illuminate\Support\Js::from($cardRoute) }}, {{ \Illuminate\Support\Js::from($arguments ?? []) }}, null, null, null, {{ \Illuminate\Support\Js::from($cardOptions) }})"
    @elseif ($cardComponent)
        @click="$modal({{ \Illuminate\Support\Js::from($cardComponent) }}, {{ \Illuminate\Support\Js::from($arguments ?? []) }})"
    @endif
    class="{{ $background ?? 'bg-white' }} border border-gray-300  hover:bg-gray-50 w-36 h-36 mr-6 mt-6 flex p-2 py-4 text-sm text-center rounded-lg items-center justify-center"
>
    <div class="m-auto">
        <div class="inline-block">
            @isset($icon)
                <img alt="" src="/assets/svg/{{ $icon }}.svg" class="mb-2 h-6 w-6" />
            @endisset
            @isset($heroicon)
                <x-icon name="{{ $heroicon }}" class="mb-2 h-6 w-6 text-gray-800" />
            @endisset
        </div>

        <div class="w-full text-gray-500">{{ $title }}</div>

        @isset($value)
            <div class="text-2xl font-semibold">{{ number_format($value, 0, ',', '.') }}</div>
        @endisset
    </div>
</a>
