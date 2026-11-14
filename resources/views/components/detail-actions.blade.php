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
    $resolveActionCondition = fn (array $action): ?string => \Noerd\Support\FieldVisibility::showExpression($action);

    $actions = collect($layout['actions'] ?? [])
        ->filter(fn(array $action): bool => $modelId || ($action['requiresId'] ?? true) === false)
        ->filter(fn(array $action): bool => empty($action['viewExists'])
            || \Illuminate\Support\Facades\View::exists($action['viewExists']))
        // A modalRoute: action whose route is not registered survives only with a
        // modalComponent fallback, so an optional module may contribute YAML safely.
        // An action naming no target at all (a key of an earlier vocabulary) is dropped.
        ->filter(fn(array $action): bool => \Noerd\Support\YamlModalTarget::declares($action)
            ? \Noerd\Support\YamlModalTarget::opens($action)
            : array_key_exists('url', $action) || ! empty($action['action']))
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
                $modalArguments = \Noerd\Support\LayoutArguments::resolve($action['arguments'] ?? [], $modelId, $__livewire ?? null);

                // modalRoute: opens a route modal, modalComponent: is its fallback (or
                // the target alone); the filter above removed actions that open neither.
                $actionModal = \Noerd\Support\YamlModalTarget::clickExpression($action, $modalArguments);

                $actionUrl = $actionModal ? null : $resolveActionUrl($action);

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
                    @if ($actionShow || $actionModal) x-data @endif
                    @if ($actionShow) x-show="{{ $actionShow }}" @endif
                    @if ($actionModal)
                        x-on:click="{{ $actionModal }}"
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
                    @if (! $actionModal && ! empty($action['loading']))
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
