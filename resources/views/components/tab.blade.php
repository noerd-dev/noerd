@props(['tabNumber' => null, 'route' => null, 'routeParameters' => [], 'component' => null, 'modalRoute' => null, 'arguments' => null, 'external' => null, 'active' => false])

@php
    // `route:` navigates, `modalRoute:` opens the record's own route as a modal.
    // `component:` remains the fallback for an unregistered modalRoute.
    $tabModalRoute = $modalRoute && \Illuminate\Support\Facades\Route::has($modalRoute) ? $modalRoute : null;
    $opensAsModal = $tabModalRoute || $component;
@endphp

@isset($tabNumber)
    <div class="inline-flex">
        <a
            @click.prevent="currentTab= {{ $tabNumber }}"
            class="mr-6 -mb-[1px] cursor-pointer border-b-2 border-transparent text-gray-600 hover:border-gray-500 focus:outline-none focus-visible:outline-none"
            :class="{'border-brand-primary! text-black!': currentTab == {{ $tabNumber }} }"
        >
            <span class="group inline-flex items-center rounded-sm border-b-2 border-transparent p-0 py-3 text-sm">
                {{ $slot }}
            </span>
        </a>
    </div>
@endisset

@if (isset($route) && ! $opensAsModal)
    <div class="inline-flex">
        <a
            @if ($external) target="_blank" @else wire:navigate @endif
            href="{{ route($route, $routeParameters ?? null) }}"
            @class([
                '-mb-[1px] border-b-2 text-gray-600 mr-6 hover:border-gray-500 focus:outline-none focus-visible:outline-none',
                'border-brand-primary! text-black!' => $active,
                'border-transparent' => !$active,
            ])
        >
            <span class="group inline-flex items-center rounded-sm border-b-2 border-transparent p-0 py-3 text-sm">
                {{ $slot }}
            </span>
        </a>
    </div>
@endif

@if ($opensAsModal)
    @php
        // A modalRoute tab points at the record's real page, so it also supplies
        // the href for cmd-click / "open in new tab".
        $componentRouteUrl = $tabModalRoute
            ? route($tabModalRoute, $routeParameters ?? [])
            : ($route ? route($route, $routeParameters) : null);

        $clickExpression = $tabModalRoute
            ? '$modalRoute(' . \Illuminate\Support\Js::from($tabModalRoute) . ', ' . \Illuminate\Support\Js::from($arguments ?? []) . ', null, null, null, ' . \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $component])) . ')'
            : '$modal(' . \Illuminate\Support\Js::from($component) . ', ' . \Illuminate\Support\Js::from($arguments ?? []) . ')';
    @endphp
    <div class="mr-6 -mb-[1px] inline-flex items-center border-b-2 border-transparent hover:border-gray-500">
        <a
            @if ($componentRouteUrl) href="{{ $componentRouteUrl }}" @endif
            @click="if (! $event.metaKey && ! $event.ctrlKey) { $event.preventDefault(); {{ $clickExpression }}; }"
            class="cursor-pointer text-gray-600 focus:outline-none focus-visible:outline-none"
        >
            <span class="group inline-flex items-center rounded-sm p-0 py-3 text-sm"> {{ $slot }} </span>
        </a>
        @if ($componentRouteUrl)
            <a
                href="{{ $componentRouteUrl }}"
                target="_blank"
                rel="noopener"
                class="py-3 pl-2 text-gray-500 hover:text-black focus:outline-none focus-visible:outline-none"
                aria-label="{{ __('Open in new tab') }}"
            >
                <x-noerd::icons.external />
            </a>
        @endif
    </div>
@endif
