<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Support\NavigationSubmenus;
use Noerd\Support\ShellState;

new class extends Component {
    /**
     * Open/closed state of the block menus, keyed exactly like $navigations.
     * Deliberately NOT locked: Alpine entangles it so the chevron reacts
     * instantly, while the component/link data stays server-owned.
     *
     * @var array<int|string, bool>
     */
    public array $expanded = [];

    #[Locked]
    public array $navigations = [];

    /**
     * The sub-navigation (`submenu: true` block) holding the current page, open
     * when the sidebar renders. Read in mount(): only the page request knows
     * its route — a Livewire update request does not. Switching between the
     * navigation and a sub-navigation afterwards is client state only.
     */
    #[Locked]
    public int|string|null $openSubmenu = null;

    public function mount(): void
    {
        foreach ($this->navigations as $key => $block) {
            if (NavigationSubmenus::isSubmenu($block)) {
                continue;
            }

            $this->expanded[$key] = (bool) ($block['show'] ?? false);
        }

        $this->openSubmenu = NavigationSubmenus::activeIndex($this->navigations, request());
    }

    public function openStatus(string $title): void
    {
        ShellState::setBlockMenuVisible($title, ! ShellState::blockMenuVisible($title));
    }
} ?>

<div :class="showSidebar ? 'pl-6 pr-5' : 'pl-3 pr-3'" style="scrollbar-gutter: stable;"
     x-data="{
        submenu: {{ \Illuminate\Support\Js::from($openSubmenu) }},
        enterSubmenu(key) {
            this.submenu = key;
            this.$root.scrollTop = 0;
            this.$nextTick(() => this.$refs['submenu-back-' + key]?.focus());
        },
        leaveSubmenu() {
            const key = this.submenu;
            this.submenu = null;
            this.$nextTick(() => this.$refs['submenu-trigger-' + key]?.focus());
        },
     }"
     class="noerd-scrollbar flex grow flex-col gap-y-3 pt-6 overflow-y-auto overflow-x-hidden pb-4 border-gray-300">

    {{-- The navigation and every sub-navigation share one grid cell, so the panel
         that leaves and the one that enters cross-fade in place. --}}
    <div class="grid">

        {{-- The navigation --}}
        <div x-show="submenu === null"
             @if ($openSubmenu !== null) style="display: none" @endif
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-x-3"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 -translate-x-3"
             class="[grid-area:1/1] min-w-0">

            @foreach($navigations as $key => $block)
                @if(\Noerd\Support\NavigationSubmenus::isSubmenu($block))
                    {{-- A sub-navigation: one entry that opens the block's entries --}}
                    <ul role="list" class="pb-2" wire:key="submenu-entry-{{ $key }}">
                        <li @class([
                                'flex group hover:bg-brand-navi-hover rounded-lg pr-1',
                                'bg-brand-primary/5' => $key === $openSubmenu,
                            ])>
                            <button type="button"
                                    x-ref="submenu-trigger-{{ $key }}"
                                    @click="enterSubmenu({{ \Illuminate\Support\Js::from($key) }})"
                                    :aria-expanded="submenu === {{ \Illuminate\Support\Js::from($key) }} ? 'true' : 'false'"
                                    class="flex flex-1 cursor-pointer items-center border-l-2 border-transparent -ml-6 pl-9 group-hover:border-gray-500 gap-x-1 text-gray-900 p-1.5 px-1 text-left text-sm">
                                @isset($block['heroicon'])
                                    <x-icon name="{{ $block['heroicon'] }}" class="w-4 h-4 mr-2 text-gray-800"/>
                                @endisset
                                <span x-show="showSidebar">{{ __($block['title']) }}</span>
                                <x-icon name="chevron-right" x-show="showSidebar" class="ml-auto w-4 h-4 shrink-0 text-gray-500"/>
                            </button>
                        </li>
                    </ul>
                @elseif(!empty($block['navigations']))
                    <div wire:key="block-{{ $key }}" x-data="{show: $wire.entangle('expanded.{{ $key }}')}">
                        <button type="button" wire:click="openStatus({{ Illuminate\Support\Js::from($block['title']) }})" @click="show = !show"
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
                            @include('noerd::components.layout.navigation-entries', [
                                'entries' => $block['navigations'],
                                'style' => $block['style'] ?? 'list',
                                'keyPrefix' => '',
                            ])
                        </div>
                    </div>
                @endif
                @if(empty($block['navigations']) && isset($block['route']))
                    <ul role="list" class="pb-2">
                        <livewire:noerd::layout.sidebar-navigation-element wire:key="nav-block-{{ $block['route'] ?? $block['title'] }}" :navi="$block"/>
                    </ul>
                @endif
            @endforeach
        </div>

        {{-- One panel per sub-navigation --}}
        @foreach($navigations as $key => $block)
            @if(\Noerd\Support\NavigationSubmenus::isSubmenu($block))
                <div wire:key="submenu-{{ $key }}"
                     x-show="submenu === {{ \Illuminate\Support\Js::from($key) }}"
                     @if ($openSubmenu !== $key) style="display: none" @endif
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-x-3"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-x-0"
                     x-transition:leave-end="opacity-0 translate-x-3"
                     class="[grid-area:1/1] min-w-0">
                    <button type="button"
                            x-ref="submenu-back-{{ $key }}"
                            @click="leaveSubmenu()"
                            class="flex w-full cursor-pointer items-center gap-x-1 py-2 text-xs font-semibold text-gray-600 hover:text-black">
                        <x-icon name="chevron-left" class="w-4 h-4 shrink-0"/>
                        <span x-show="showSidebar">{{ __('Back') }}</span>
                    </button>
                    <div x-show="showSidebar" class="flex items-center gap-x-2 pt-3 pb-1 text-sm font-semibold text-gray-900">
                        @isset($block['heroicon'])
                            <x-icon name="{{ $block['heroicon'] }}" class="w-4 h-4 text-gray-800"/>
                        @endisset
                        {{ __($block['title']) }}
                    </div>
                    <div class="pt-2 pb-2">
                        @include('noerd::components.layout.navigation-entries', [
                            'entries' => $block['navigations'],
                            'style' => $block['style'] ?? 'list',
                            'keyPrefix' => 'sub-'.$key.'-',
                        ])
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
