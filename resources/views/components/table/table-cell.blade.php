<td class="py-1 first:pl-4 last:border-r-0 border-gray-300 border-r border-b"
    @click="activeColumn = {{$column}}, selectedRow = {{$row}}"
    x-data="{showDropdown: false}"
    {{--
    x-noerd::on:keydown.arrow-down.prevent="down()"
    x-noerd::on:keydown.arrow-up.prevent="up()"
    x-noerd::on:keydown.arrow-left.prevent="left()"
    x-noerd::on:keydown.arrow-right.prevent="right()"
    --}}
>

    @if($columnValue === 'action')
        <div class="flex ml-auto mr-1" disabled wire:navigate>

            @if($actions)
                <div :class="showDropdown ? 'opacity-100' : 'opacity-0'"
                     class="relative inline-block text-left ml-auto opacity-0 group-hover:opacity-100">
                    <button @click.outside="showDropdown = false" @click="showDropdown = !showDropdown" type="button"
                            class="inline-flex h-full w-full justify-center rounded-md bg-white px-3 py-1 text-xs font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                            id="menu-button" aria-expanded="true" aria-haspopup="true">
                        <svg class="my-auto" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                             viewBox="0 0 16 16">
                            <title>menu-dots</title>
                            <g fill="#333">
                                <circle fill="#333" cx="8" cy="8" r="2"></circle>
                                <circle fill="#333" cx="2" cy="8" r="2"></circle>
                                <circle fill="#333" cx="14" cy="8" r="2"></circle>
                            </g>
                        </svg>
                    </button>

                    <div x-transition x-show="showDropdown"
                         class="absolute  right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                         role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                        <div class="py-1" role="none">
                            @foreach($actions as $action)
                                <a wire:click.prevent="{{$action['action']}}('{{$id}}')"
                                   @isset($action['confirm'])
                                       wire:confirm="{{$action['confirm']}}"
                                   @endisset
                                   class="cursor-pointer group flex items-center px-4 py-2 text-sm text-gray-700"
                                   role="menuitem" tabindex="-1" id="menu-item-0">
                                    @isset($action['heroicon'])
                                        <x-icon name="{{$action['heroicon']}}" class="w-4 h-4 mr-2 text-gray-800"/>
                                    @endisset
                                    {{ $action['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <button
                    class="opacity-0 ml-auto mr-1 group-hover:opacity-100  my-auto bg-white shadow-sm hover:bg-gray-50 flex h-6 px-1.5 text-sm text-center rounded-lg items-center justify-center">
                    <div class="m-auto">
                        <x-noerd::icons.pencil class="w-3! h-3!"/>
                    </div>
                </button>
            @endif
        </div>
    @elseif($columnValue === 'selectAction')
        <a class="m-0.5 flex"
           @click="show = !show"
           wire:navigate

           wire:click.prevent="{{$action}}('{{$redirectAction}}')"
        >
            <x-noerd::buttons.primary icon="noerd::icons.plus-circle" class="ml-auto">
                {{ __($label) }}
            </x-noerd::buttons.primary>
        </a>
    @elseif($columnValue === 'deleteAction')
        <a class="m-0.5 flex" wire:confirm="{{ __('Are you sure you want to delete your account?') }}" wire:navigate

           wire:click.prevent="{{$action}}('{{$id}}')">
            <x-noerd::buttons.small.delete class="ml-auto">
                {{ __($label) }}
            </x-noerd::buttons.small.delete>
        </a>
    @elseif($columnValue === 'secondAction')
        <a class="m-0.5 flex" wire:navigate

           wire:click.prevent="{{$action}}('{{$id}}')">
            <x-noerd::buttons.secondary class="ml-auto">
                {{ __($label) }}
            </x-noerd::buttons.secondary>
        </a>
    @else
        @if($type === 'bool')
            @if($value == true)
                <div
                    wire:click.prevent="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}',{{0}})"
                    class="px-3 tw-shrink-0 text-right">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-6 h-6 text-green-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            @else
                <div
                    wire:click.prevent="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}',{{1}})"
                    class="px-3 tw-shrink-0 text-right">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-6 h-6 text-red-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            @endif
        @elseif($type === 'inversebool')
            @if($value == true)
                <div
                    wire:click.prevent="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}',{{0}})"
                    class="px-3 tw-shrink-0 text-right">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-6 h-6 text-green-400">
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
                           wire:click.prevent="{{$action}}('{{$redirectAction}}')"
                           @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                           class="cursor-pointer border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5  @if(in_array($type, ['number'])) text-right @endif"
                           value="{{$value}}">
                @endif
            @elseif($type == 'number')
                <input type="{{$type}}"
                       wire:click.prevent="{{$action}}('{{$redirectAction}}')"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5  @if(in_array($type, ['number'])) text-right @endif"
                       value="{{round((float)$value,2)}}">
            @elseif($type == 'currency')
                <input type="{{$type}}"
                       wire:click.prevent="{{$action}}('{{$redirectAction}}')"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 text-right"
                       value="{{number_format((float)$value,2, ',', '.')}} €">
            @else
                <input type="{{$type}}"
                       wire:click.prevent="{{$action}}('{{$redirectAction}}')"
                       wire:change="updateRow({{$id ?? null}}, '{{$columnValue ?? null}}', $event.target.value)"
                       @if($readOnly ?? true) readonly @endif id="cell-{{$column}}-{{$row}}"
                       class="cursor-pointer border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5"
                       value="{{$value}}">

            @endif
        @endif
    @endif
</td>
