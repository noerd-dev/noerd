<td class="py-1" @click="activeColumn = {{$column}}, activeRow = {{$row}}"
    {{--}}
    x-noerd::on:keydown.arrow-down.prevent="down()"
    x-noerd::on:keydown.arrow-up.prevent="up()"
    x-noerd::on:keydown.arrow-left.prevent="left()"
    x-noerd::on:keydown.arrow-right.prevent="right()"
    --}}
    wire:keydown.enter.prevent="{{$action}}('{{$redirectAction}}')"
>

    @if($columnValue === 'action')
        <a x-data="{showDropdown: false}" class="m-0.5 flex" wire:navigate
          >
            {{--
            <button x-show="showSidebar"
                    class="opacity-0 ml-auto mr-1 group-hover:opacity-100  my-auto bg-white shadow-sm hover:bg-gray-50 flex h-6 px-1.5 text-sm text-center rounded-lg items-center justify-center">
                <div class="m-auto">
                    <x-noerd::icons.pencil class="w-3! h-3!"/>
                </div>
            </button>

            --}}

            <div class="relative inline-block text-left">
                <div>
                    <button type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50" id="menu-button" aria-expanded="true" aria-haspopup="true">
                        Options
                        <svg class="-mr-1 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <!--
                  Dropdown menu, show/hide based on menu state.

                  Entering: "transition ease-out duration-100"
                    From: "transform opacity-0 scale-95"
                    To: "transform opacity-100 scale-100"
                  Leaving: "transition ease-in duration-75"
                    From: "transform opacity-100 scale-100"
                    To: "transform opacity-0 scale-95"
                -->
                <div x-show="showDropdown" class="absolute right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                    <div class="py-1" role="none">
                        <!-- Active: "bg-gray-100 text-gray-900 outline-hidden", Not Active: "text-gray-700" -->
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-0">Edit</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-1">Duplicate</a>
                    </div>
                    <div class="py-1" role="none">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-2">Archive</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-3">Move</a>
                    </div>
                    <div class="py-1" role="none">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-4">Share</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-5">Add to favorites</a>
                    </div>
                    <div class="py-1" role="none">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabindex="-1" id="menu-item-6">Delete</a>
                    </div>
                </div>
            </div>

        </a>
    @elseif($columnValue === 'selectAction')
        <a class="m-0.5 flex" wire:navigate
           {{--
           @click="show = !show" wire:click.prevent="{{$action}}('{{$redirectAction}}')"
           --}}
        >
            <x-noerd::buttons.primary icon="noerd::icons.plus-circle" class="ml-auto">
                {{$label}}
            </x-noerd::buttons.primary>
        </a>
    @elseif($columnValue === 'deleteAction')
        <a class="m-0.5 flex" wire:confirm="{{ __('Really delete position?') }}" wire:navigate
           wire:click.prevent="{{$action}}('{{$id}}')">
            <x-noerd::buttons.small.delete class="ml-auto">
                {{$label}}
            </x-noerd::buttons.small.delete>
        </a>
    @elseif($columnValue === 'secondAction')
        <a class="m-0.5 flex" wire:navigate
           wire:click.prevent="{{$action}}('{{$id}}')">
            <x-noerd::buttons.secondary class="ml-auto">
                {{$label}}
            </x-noerd::buttons.secondary>
        </a>
    @else
        @if($type === 'bool')
            @if($value == true)
                <div wire:click="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}',{{0}})"
                     class="px-3 tw-shrink-0 text-right">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-6 h-6 text-green-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            @else
                <div wire:click="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}',{{1}})"
                     class="px-3 tw-shrink-0 text-right">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-6 h-6 text-red-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            @endif
        @else
            @if($type == 'id')
                <a wire:navigate class="bg-gray-100"
                   wire:click.prevent="{{$action}}('{{$redirectAction}}')">
                    <input type="text"
                           wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                           @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                           class="cursor-pointer underline w-auto border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                           value="{{$value}}">
                </a>
            @elseif($type == 'date')
                @if($value)
                    <input type="{{$type}}"
                           wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                           @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                           class="cursor-pointer border-2! border-transparent ring-0! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                           :class="{'border-2! border-indigo-400! bg-indigo-100': activeColumn === {{$column}} && activeRow == {{$row}} }"
                           value="{{$value}}">
                @endif
            @elseif($type == 'number')
                <input type="{{$type}}"
                       wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer border-2! border-transparent ring-0! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                       :class="{'border-2! border-indigo-400! bg-indigo-100': activeColumn === {{$column}} && activeRow == {{$row}} }"
                       value="{{round($value,2)}}">
            @elseif($type == 'currency')
                <input type="{{$type}}"
                       wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer text-right border-2! border-transparent ring-0! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                       :class="{'border-2! border-indigo-400! bg-indigo-100': activeColumn === {{$column}} && activeRow == {{$row}} }"
                       value="{{number_format($value,2, ',', '.')}} €">
            @else
                <input type="{{$type}}"
                       wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer hover:underline border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                       value="{{$value}}">
            @endif
        @endif
    @endif
</td>
