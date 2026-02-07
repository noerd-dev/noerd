<?php

use Livewire\Component;

new class extends Component {
    public $navigation = [];
    public $navigations = [];

    public function openStatus(string $title): void
    {
        if (session('navi_hidden_' . $title)) {
            session()->forget('navi_hidden_' . $title);
        } else {
            session(['navi_hidden_' . $title => true]);
        }
    }
} ?>

<div :class="showSidebar ? 'pl-6 pr-5' : 'pl-3 pr-3'" style="scrollbar-gutter: stable;"
     class="flex my-auto grow flex-col gap-y-3 pt-6 overflow-y-auto pb-4 border-gray-300">

    <div>

        {{-- if its an block menu --}}
        @foreach($navigations as $key => $block)
            @if(!empty($block['navigations']))
                <div x-data="{show: $wire.entangle('navigations.{{ $key }}.show')}">
                    <button type="button" wire:click="openStatus('{{$block['title']}}')" @click="show = !show"
                            class="hover:text-black w-full py-2">
                        <div class="font-bold text-xs flex">
                            <div class="font-semibold text-gray-600" x-show="showSidebar">
                                {{ __($block['title']) }}
                            </div>
                            <div x-show="show && showSidebar" class="ml-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5"
                                     stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div x-show="!show && showSidebar" class="ml-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5"
                                     stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                    <div x-show="show || !showSidebar" class="pt-2 pb-2">
                        <ul role="list" class="space-y-1">
                            @foreach($block['navigations'] as $navi)
                                <livewire:layout.sidebar-navigation-element wire:key="{{$navi['title']}}"
                                                                            :navi="$navi"/>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @if(empty($block['navigations']) && isset($block['route']))
                <ul role="list" class="pb-2">
                    <livewire:layout.sidebar-navigation-element wire:key="{{$block['title']}}" :navi="$block"/>
                </ul>
            @endif
        @endforeach

        {{-- if its just an submenu --}}
        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    <ul role="list" class="-mx-2 space-y-1" :class="showSidebar ? 'w-full' : ''">
                        @foreach($navigation as $navi)
                            @livewire('layout.sidebar-navigation-element', ['navi' => $navi])
                        @endforeach
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div>
