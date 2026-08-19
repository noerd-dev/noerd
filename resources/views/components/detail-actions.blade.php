@props([
    'layout' => [],
    'modelId' => null,
    'urls' => [],
])

@php
    // A url: action either carries a literal URL or names a key in the urls map
    // the detail component passes in (so the target may depend on the record).
    $resolveActionUrl = function (array $action) use ($urls): ?string {
        $url = $action['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
            return $url;
        }

        $resolved = $urls[$url] ?? null;

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    };

    // showIf/showIfNot mirror the field- and tab-level conditions: an Alpine
    // x-show on the button, so an action follows the component state (e.g. an
    // invite button that disappears once the customer account exists).
    $resolveActionCondition = function (array $action): ?string {
        $expressions = [];

        foreach (['showIf' => '', 'showIfNot' => '!'] as $key => $negation) {
            if (! isset($action[$key])) {
                continue;
            }

            $condition = $action[$key];

            $expressions[] = is_array($condition)
                ? $negation . "(\$wire." . $condition['field'] . " === '" . $condition['value'] . "')"
                : $negation . '$wire.' . $condition;
        }

        return $expressions === [] ? null : implode(' && ', $expressions);
    };

    $actions = collect($layout['actions'] ?? [])
        ->filter(fn(array $action): bool => $modelId || ($action['requiresId'] ?? true) === false)
        ->filter(fn(array $action): bool => empty($action['viewExists'])
            || \Illuminate\Support\Facades\View::exists($action['viewExists']))
        // A route: action whose route is not registered survives only with a
        // modalComponent fallback, so an optional module may contribute YAML safely.
        ->filter(fn(array $action): bool => empty($action['route'])
            || \Illuminate\Support\Facades\Route::has($action['route'])
            || ! empty($action['modalComponent']))
        // A url: action without a resolvable URL is dropped instead of rendering a dead link.
        ->filter(fn(array $action): bool => ! array_key_exists('url', $action)
            || $resolveActionUrl($action) !== null)
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
    $actionClasses = 'inline-flex cursor-pointer items-center gap-1.5 border border-gray-300 bg-white font-medium text-gray-700 shadow-xs hover:bg-gray-100 ' . $actionSizeClasses;

    // When EVERY action is conditional the bar itself follows the same conditions,
    // so a fully hidden row never leaves an empty gray box behind.
    $barConditions = $actions->map($resolveActionCondition);
    $barShow = $barConditions->filter()->count() === $actions->count() && $actions->isNotEmpty()
        ? $barConditions->map(fn(string $condition): string => '(' . $condition . ')')->implode(' || ')
        : null;
@endphp

@if ($actions->isNotEmpty())
    <div
        @if ($barShow) x-data x-show="{{ $barShow }}" @endif
        class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
    >
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

                $actionUrl = $actionRoute || ! empty($action['modalComponent'])
                    ? null
                    : $resolveActionUrl($action);

                $actionShow = $resolveActionCondition($action);
            @endphp
            @if ($actionUrl)
                <a
                    href="{{ $actionUrl }}"
                    @if ($actionShow) x-data x-show="{{ $actionShow }}" @endif
                    @if (($action['newTab'] ?? true)) target="_blank" rel="noopener" @endif
                    class="{{ $actionClasses }}"
                >
                    @if (! empty($action['heroicon']))
                        <x-icon name="{{ $action['heroicon'] }}" class="h-4 w-4 text-gray-500" />
                    @endif
                    {{ __($action['label']) }}
                </a>
            @else
                <button
                    type="button"
                    @if ($actionShow || $actionRoute || ! empty($action['modalComponent'])) x-data @endif
                    @if ($actionShow) x-show="{{ $actionShow }}" @endif
                    @if ($actionRoute)
                        x-on:click="$modalRoute({{ \Illuminate\Support\Js::from($actionRoute) }},{{ \Illuminate\Support\Js::from($modalArguments) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $action['modalComponent'] ?? null])) }})"
                    @elseif (! empty($action['modalComponent']))
                        x-on:click="$modal({{ \Illuminate\Support\Js::from($action['modalComponent']) }}, {{ \Illuminate\Support\Js::from($modalArguments) }})"
                    @else
                        wire:click="{{ $action['action'] }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $action['action'] }}"
                        @if (! empty($action['confirm'])) wire:confirm="{{ __($action['confirm']) }}" @endif
                    @endif
                    class="{{ $actionClasses }}"
                >
                    @if (! empty($action['heroicon']))
                        <x-icon name="{{ $action['heroicon'] }}" class="h-4 w-4 text-gray-500" />
                    @endif
                    @if (empty($actionRoute) && empty($action['modalComponent']) && ! empty($action['loading']))
                        <span
                            wire:loading.remove
                            wire:target="{{ $action['action'] }}"
                        >{{ __($action['label']) }}</span>
                        <span wire:loading wire:target="{{ $action['action'] }}">{{ __($action['loading']) }}</span>
                    @else
                        {{ __($action['label']) }}
                    @endif
                </button>
            @endif
        @endforeach
    </div>
@endif
