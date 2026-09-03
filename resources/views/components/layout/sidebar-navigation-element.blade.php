<?php

use Livewire\Component;

new class extends Component {
    public array $navi = [];
} ?>

@php
    // `route:` means NAVIGATE (it also drives the active-state highlight below),
    // so opening an entry as a modal uses the separate `modalRoute:` key.
    // `component:` stays as the fallback for an unregistered route name.
    $naviModalRoute = ($navi['modalRoute'] ?? null);
    $naviModalRoute = $naviModalRoute && \Illuminate\Support\Facades\Route::has($naviModalRoute) ? $naviModalRoute : null;
    $naviModalComponent = $navi['component'] ?? null;
    $opensAsModal = $naviModalRoute || $naviModalComponent;
    $naviArguments = is_array($navi['arguments'] ?? null) ? $navi['arguments'] : [];

    // The primary `route:` may belong to an optional module. A stale navigation
    // entry must never take the whole page down, so a route-only entry whose
    // route is not registered is skipped entirely.
    $routeName = $navi['route'] ?? '';
    $routeExists = $routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName);
    $hasTarget = isset($navi['link']) || $opensAsModal || $routeExists;
@endphp

{{-- The root <li> must be the FIRST element of this view and start on its own
     line: Livewire detects a component's root element with a regex that only
     matches a tag preceded by a newline, and it remembers that tag to build the
     placeholder used whenever the PARENT re-renders. Wrapping the root in
     @if/@else makes Blade swallow the newline, so Livewire stamps wire:id onto a
     nested <div> instead — and the whole entry loses its markup the moment the
     sidebar re-renders. Keep the conditional INSIDE the root element. --}}

<li class="{{ $hasTarget ? '' : 'hidden' }} {{ (isset($navi['link']) ? request()->is(ltrim($navi['link'], '/')) : request()->routeIs($navi['route'] ?? null))  ? 'bg-brand-primary/5' : '' }} flex group hover:bg-brand-navi-hover rounded-lg pr-1">
    @if ($hasTarget)
    @if ($opensAsModal)
        <button type="button"
                @if ($naviModalRoute)
                    @click="$modalRoute({{ \Illuminate\Support\Js::from($naviModalRoute) }}, {{ \Illuminate\Support\Js::from($naviArguments) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $naviModalComponent])) }}); if(! isDesktop) showSidebar = false"
                @else
                    @click="$modal({{ \Illuminate\Support\Js::from($naviModalComponent) }}, {{ \Illuminate\Support\Js::from($naviArguments) }}); if(! isDesktop) showSidebar = false"
                @endif
                class="border-l-2 cursor-pointer  border-transparent pl-3 group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ __($navi['title']) }}
            </div>
        </button>
    @endif
    @isset($navi['link'])
        <a wire:navigate href="{{ $navi['link'] }}" @isset($navi['external']) target="_blank" @endisset
        @click="if(! isDesktop) showSidebar = false"
        class="{{ request()->is(ltrim($navi['link'], '/'))  ? 'border-brand-primary!' : '' }} border-l-2 -ml-6 pl-9 group-hover:border-gray-500  border-transparent group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ __($navi['title']) }}

                @isset($navi['external'])
                    <x-noerd::icons.external/>
                @endisset
            </div>
        </a>
    @elseif ($opensAsModal)
        {{-- Already rendered as the modal anchor above. --}}
    @else
        <a wire:navigate href="{{ route($routeName) }}" @isset($navi['external']) target="_blank" @endisset
        @click="if(! isDesktop) showSidebar = false"
        class="{{ request()->routeIs($routeName)  ? 'border-brand-primary! ' : '' }} flex-1 border-l-2 -ml-6 pl-9 group-hover:border-gray-500  border-transparent group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ __($navi['title']) }}

                @isset($navi['external'])
                    <x-noerd::icons.external/>
                @endisset
            </div>
        </a>

        @php
            // The "+" button creates a record: `newRoute:` opens the detail route
            // (the URL becomes /{app}/{entity}/new?modal=true), `newComponent:`
            // remains the fallback.
            $newRoute = ($navi['newRoute'] ?? null);
            $newRoute = $newRoute && \Illuminate\Support\Facades\Route::has($newRoute) ? $newRoute : null;
            $newComponent = $navi['newComponent'] ?? null;
            $isQuickCreate = $navi['quickCreate'] ?? false;
            $newArguments = $isQuickCreate
                ? array_merge($naviArguments, ['modelId' => null, 'quickCreate' => true])
                : $naviArguments;
            $newSize = $isQuickCreate ? 'narrow' : null;
        @endphp
        @if ($newRoute || $newComponent)
            <button x-show="showSidebar"
                    type="button"
                    aria-label="{{ __('New Entry') }}"
                    @if ($newRoute)
                        @click="$modalRoute({{ \Illuminate\Support\Js::from($newRoute) }}, {{ \Illuminate\Support\Js::from($newArguments) }}, null, null, {{ \Illuminate\Support\Js::from($newSize) }}, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $newComponent])) }})"
                    @else
                        @click="$modal({{ \Illuminate\Support\Js::from($newComponent) }}, {{ \Illuminate\Support\Js::from($newArguments) }}, null, null, {{ \Illuminate\Support\Js::from($newSize) }})"
                    @endif
                    class="ml-auto my-auto border-gray-300 border  hover:bg-gray-200 flex h-6 px-1.5 text-sm text-center rounded-lg items-center justify-center">
                <div class="m-auto">
                    <x-noerd::icons.plus class="w-3! h-3!"/>
                </div>
            </button>
        @endif
    @endisset
    @endif
</li>
