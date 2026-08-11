<?php

use Livewire\Component;

new class extends Component {
    public $navi = [];
} ?>

@php
    $routeName = ($navi['route'] ?? null) === 'collections' ? 'cms.collections' : ($navi['route'] ?? '');
    $isActive = isset($navi['link'])
        ? request()->is(ltrim($navi['link'], '/'))
        : request()->routeIs($routeName);
    $activeClass = $isActive ? 'border-brand-primary bg-brand-primary/5' : 'border-gray-200 hover:border-gray-400 hover:bg-gray-50';

    // `route:` means NAVIGATE (and drives $isActive above), so opening an entry as
    // a modal uses `modalRoute:`; `component:` stays as the fallback.
    $naviModalRoute = ($navi['modalRoute'] ?? null);
    $naviModalRoute = $naviModalRoute && \Illuminate\Support\Facades\Route::has($naviModalRoute) ? $naviModalRoute : null;
    $naviModalComponent = $navi['component'] ?? null;
    $opensAsModal = $naviModalRoute || $naviModalComponent;
    $naviArguments = $arguments ?? [];

    // The primary `route:` may belong to an optional module. A stale navigation
    // entry must never take the whole page down, so a route-only entry whose
    // route is not registered is skipped entirely.
    $routeExists = $routeName !== '' && \Illuminate\Support\Facades\Route::has($routeName);
    $hasTarget = isset($navi['link']) || $opensAsModal || $routeExists;
@endphp

@if (! $hasTarget)
    {{-- Livewire needs an unconditional root element. --}}
    <div class="hidden"></div>
@else
<div>
    @if ($opensAsModal)
        <a @if ($naviModalRoute)
               @click="$modalRoute({{ \Illuminate\Support\Js::from($naviModalRoute) }}, {{ \Illuminate\Support\Js::from($naviArguments) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $naviModalComponent])) }}); if(! isDesktop) showSidebar = false"
           @else
               @click="$modal({{ \Illuminate\Support\Js::from($naviModalComponent) }}, {{ \Illuminate\Support\Js::from($naviArguments) }}); if(! isDesktop) showSidebar = false"
           @endif
           class="{{ $activeClass }} flex cursor-pointer items-center gap-x-3 rounded-xl border p-3 text-gray-900 transition-colors">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-6 h-6 shrink-0 text-gray-700"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-6 h-6 shrink-0 text-gray-700"/>
            @endisset
            <div x-show="showSidebar" class="text-sm font-medium">
                {{ __($navi['title']) }}
            </div>
        </a>
    @endif

    @isset($navi['link'])
        @if (! $opensAsModal)
            <a wire:navigate href="{{ $navi['link'] }}" @isset($navi['external']) target="_blank" @endisset
               @click="if(! isDesktop) showSidebar = false"
               class="{{ $activeClass }} flex items-center gap-x-3 rounded-xl border p-3 text-gray-900 transition-colors">
                @isset($navi['icon'])
                    <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-6 h-6 shrink-0 text-gray-700"/>
                @endisset
                @isset($navi['heroicon'])
                    <x-icon name="{{$navi['heroicon']}}" class="w-6 h-6 shrink-0 text-gray-700"/>
                @endisset
                <div x-show="showSidebar" class="text-sm font-medium">
                    {{ __($navi['title']) }}
                    @isset($navi['external'])
                        <x-noerd::icons.external/>
                    @endisset
                </div>
            </a>
        @endif
    @elseif (! $opensAsModal)
        <a wire:navigate href="{{ route($routeName) }}" @isset($navi['external']) target="_blank" @endisset
           @click="if(! isDesktop) showSidebar = false"
           class="{{ $activeClass }} flex items-center gap-x-3 rounded-xl border p-3 text-gray-900 transition-colors">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-6 h-6 shrink-0 text-gray-700"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-6 h-6 shrink-0 text-gray-700"/>
            @endisset
            <div x-show="showSidebar" class="text-sm font-medium">
                {{ __($navi['title']) }}
                @isset($navi['external'])
                    <x-noerd::icons.external/>
                @endisset
            </div>
        </a>
    @endisset
</div>
@endif
