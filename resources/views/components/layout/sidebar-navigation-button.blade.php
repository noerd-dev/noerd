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
@endphp

<div>
    @isset($navi['component'])
        <a @click="$modal('{{$navi['component']}}', {{json_encode($arguments ?? [])}}); if(window.innerWidth < 1024) showSidebar = false"
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
    @endisset

    @isset($navi['link'])
        @if(!isset($navi['component']))
            <a wire:navigate href="{{ $navi['link'] }}" @isset($navi['external']) target="_blank" @endisset
               @click="if(window.innerWidth < 1024) showSidebar = false"
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
    @elseif(!isset($navi['component']))
        <a wire:navigate href="{{ route($routeName) }}" @isset($navi['external']) target="_blank" @endisset
           @click="if(window.innerWidth < 1024) showSidebar = false"
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
