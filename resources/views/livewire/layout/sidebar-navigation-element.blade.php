<?php

use Livewire\Volt\Component;

new class extends Component {
    public $navi = [];
} ?>

<li class="{{ request()->routeIs($navi['link'] ?? $navi['route'])  ? 'bg-brand-highlight/5' : '' }} flex group hover:bg-brand-navi-hover rounded-lg pr-1">
    @isset($navi['component'])
        <a wire:click="$dispatch('noerdModal', {component: '{{$navi['component']}}', arguments: {{json_encode($arguments ?? [])}}})"
           class="border-l-2 cursor-pointer  border-transparent pl-3 group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ $navi['title'] }}
            </div>
        </a>
    @endif
    @isset($navi['link'])
        <a wire:navigate href="{{ $navi['link'] }}" @isset($navi['external']) target="_blank" @endisset
        class="{{ request()->routeIs($navi['link'])  ? 'border-brand-highlight!' : '' }} border-l-2 -ml-6 pl-9 group-hover:border-gray-500  border-transparent group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ $navi['title'] }}

                @isset($navi['external'])
                    <x-noerd::icons.external/>
                @endisset
            </div>
        </a>
    @else
        <a wire:navigate href="{{ route($navi['route']) }}" @isset($navi['external']) target="_blank" @endisset
        class="{{ request()->routeIs($navi['route'])  ? '!border-brand-highlight !text-brand-highlight ' : '' }} flex-1 border-l-2 -ml-6 pl-9 group-hover:border-gray-500  border-transparent group flex gap-x-1 text-gray-900 p-1.5 px-1 text-sm">
            @isset($navi['icon'])
                <x-dynamic-component :component="'noerd::'.$navi['icon']" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset
            @isset($navi['heroicon'])
                <x-icon name="{{$navi['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
            @endisset

            <div x-show="showSidebar">
                {{ $navi['title'] }}

                @isset($navi['external'])
                    <x-noerd::icons.external/>
                @endisset
            </div>
        </a>

        @isset($navi['newComponent'])
            <button x-show="showSidebar"
                    wire:click="$dispatch('noerdModal', {component: '{{$navi['newComponent']}}', arguments: {{json_encode($arguments ?? [])}}})"
                    class="ml-auto my-auto border-gray-300 border  hover:bg-gray-200 flex h-6 px-1.5 text-sm text-center rounded-lg items-center justify-center">
                <div class="m-auto">
                    <x-noerd::icons.plus class="w-3! h-3!"/>
                </div>
            </button>
        @endisset
    @endisset
</li>
