@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $actions = collect($layout['actions'] ?? [])
        ->filter(fn(array $action): bool => $modelId || ($action['requiresId'] ?? true) === false)
        ->filter(fn(array $action): bool => empty($action['viewExists'])
            || \Illuminate\Support\Facades\View::exists($action['viewExists']))
        // A route: action whose route is not registered survives only with a
        // modalComponent fallback, so an optional module may contribute YAML safely.
        ->filter(fn(array $action): bool => empty($action['route'])
            || \Illuminate\Support\Facades\Route::has($action['route'])
            || ! empty($action['modalComponent']))
        ->values();

    // The action buttons follow the active theme (set by the rendering
    // detail/page component) instead of hardcoding their size. A theme's
    // buttonClasses may carry its own corner rounding (e.g. numbered's
    // square rounded-none) — only then skip the default rounded-md.
    $actionTheme = app(\Noerd\Services\ThemeRegistry::class)
        ->get(\Noerd\Support\ThemeContext::current() ?? 'default');
    $actionSizeClasses = $actionTheme->buttonClasses ?? 'px-2.5 py-1.5 text-xs';
    if (! str_contains($actionSizeClasses, 'rounded')) {
        $actionSizeClasses .= ' rounded-md';
    }
@endphp

@if ($actions->isNotEmpty())
    <div class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 first:mt-6">
        @foreach ($actions as $action)
            @php
                $modalArguments = collect($action['arguments'] ?? [])
                    ->map(fn($value) => $value === '$modelId' ? $modelId : $value)
                    ->all();

                // An unregistered route drops to the modalComponent branch (the
                // filter above already removed actions that have neither).
                $actionRoute = $action['route'] ?? null;
                $actionRoute = $actionRoute && \Illuminate\Support\Facades\Route::has($actionRoute)
                    ? $actionRoute
                    : null;
            @endphp
            <button
                type="button"
                @if ($actionRoute)
                    x-data
                    x-on:click="$modalRoute({{ \Illuminate\Support\Js::from($actionRoute) }},{{ \Illuminate\Support\Js::from($modalArguments) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $action['modalComponent'] ?? null])) }})"
                @elseif (! empty($action['modalComponent']))
                    x-data
                    x-on:click="$modal({{ \Illuminate\Support\Js::from($action['modalComponent']) }}, {{ \Illuminate\Support\Js::from($modalArguments) }})"
                @else
                    wire:click="{{ $action['action'] }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $action['action'] }}"
                    @if (! empty($action['confirm'])) wire:confirm="{{ __($action['confirm']) }}" @endif
                @endif
                class="inline-flex cursor-pointer items-center gap-1.5 border border-gray-300 bg-white font-medium text-gray-700 shadow-xs hover:bg-gray-100 {{ $actionSizeClasses }}"
            >
                @if (! empty($action['heroicon']))
                    <x-icon name="{{ $action['heroicon'] }}" class="h-4 w-4 text-gray-500" />
                @endif
                @if (empty($actionRoute) && empty($action['modalComponent']) && ! empty($action['loading']))
                    <span wire:loading.remove wire:target="{{ $action['action'] }}">{{ __($action['label']) }}</span>
                    <span wire:loading wire:target="{{ $action['action'] }}">{{ __($action['loading']) }}</span>
                @else
                    {{ __($action['label']) }}
                @endif
            </button>
        @endforeach
    </div>
@endif
