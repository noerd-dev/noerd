@php
    // A column may declare `translatable: true` — the cell then carries a subtle blue
    // tint so a language-dependent value is recognisable at a glance in the list.
    $isTranslatableCell = (bool) ($columnConfig['translatable'] ?? false);

    // Element ids must be unique across every list on the page — two lists
    // rendering the same row/column would otherwise collide.
    $cellId = ($listId ?? 'list') . '-' . $column . '-' . $row;

    // The column the formatter reads: the cell's own field and type, the YAML
    // column for everything else (options, …).
    $formatColumn = array_merge($columnConfig ?? [], ['field' => $columnValue, 'type' => $type]);
@endphp

<td
    class="border-r border-b border-gray-300 py-1 first:pl-4 last:border-r-0 {{ $isTranslatableCell ? 'bg-sky-50 group-hover:bg-transparent' : '' }}"
>
    @if ($columnValue === 'action')
        <div class="mr-1 ml-auto flex">
            @if ($actions)
                <div
                    x-data="{ showDropdown: false }"
                    :class="showDropdown ? 'opacity-100' : 'opacity-0'"
                    @click.stop
                    class="relative ml-auto inline-block text-left opacity-0 group-hover:opacity-100"
                >
                    <button
                        @click.outside="showDropdown = false"
                        @click="showDropdown = ! showDropdown"
                        type="button"
                        class="inline-flex h-full w-full justify-center rounded-md bg-white px-3 py-1 text-xs font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                        id="row-menu-button-{{ $listId ?? 'list' }}-{{ $id }}"
                        :aria-expanded="showDropdown ? 'true' : 'false'"
                        aria-haspopup="true"
                        aria-label="{{ __('Actions') }}"
                    >
                        <svg
                            class="my-auto text-zinc-600"
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                        >
                            <title>menu-dots</title>
                            <g fill="currentColor">
                                <circle cx="8" cy="8" r="2"></circle>
                                <circle cx="2" cy="8" r="2"></circle>
                                <circle cx="14" cy="8" r="2"></circle>
                            </g>
                        </svg>
                    </button>

                    <div
                        x-transition
                        x-show="showDropdown"
                        class="absolute right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                        role="menu"
                        aria-orientation="vertical"
                        aria-labelledby="row-menu-button-{{ $listId ?? 'list' }}-{{ $id }}"
                        tabindex="-1"
                    >
                        <div class="py-1" role="none">
                            @foreach ($actions as $action)
                                @php
                                    // Mirrors detail-actions: route: opens a route modal for the
                                    // row record, modalComponent: is the fallback, action: calls
                                    // a method on the list component. An unregistered route
                                    // without a fallback hides the entry.
                                    $rowActionRoute = $action['route'] ?? null;
                                    $rowActionRoute = $rowActionRoute && \Illuminate\Support\Facades\Route::has($rowActionRoute)
                                        ? $rowActionRoute
                                        : null;
                                @endphp
                                @if (! empty($action['route']) && ! $rowActionRoute && empty($action['modalComponent']))
                                    @continue
                                @endif
                                <button
                                    type="button"
                                    wire:key="row-action-{{ $id }}-{{ $loop->index }}"
                                    @if ($rowActionRoute)
                                        x-on:click.prevent="$modalRoute({{ \Illuminate\Support\Js::from($rowActionRoute) }}, {{ \Illuminate\Support\Js::from(['modelId' => $id]) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $action['modalComponent'] ?? null])) }})"
                                    @elseif (! empty($action['modalComponent']))
                                        x-on:click.prevent="$modal({{ \Illuminate\Support\Js::from($action['modalComponent']) }}, {{ \Illuminate\Support\Js::from(['modelId' => $id]) }})"
                                    @else
                                        wire:click.prevent="{{ $action['action'] }}('{{ $id }}')"
                                        @isset($action['confirm'])
                                            wire:confirm="{{ __($action['confirm']) }}"
                                        @endisset
                                    @endif
                                    class="group flex w-full cursor-pointer items-center px-4 py-2 text-left text-sm text-gray-700"
                                    role="menuitem"
                                    tabindex="-1"
                                >
                                    @isset($action['heroicon'])
                                        <x-icon name="{{ $action['heroicon'] }}" class="mr-2 h-4 w-4 text-gray-800" />
                                    @endisset
                                    {{ __($action['label']) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                {{-- Purely decorative row affordance: the row itself is clickable,
                     this carries no handler and must not be focusable. --}}
                <span
                    aria-hidden="true"
                    class="my-auto mr-1 ml-auto flex h-6 items-center justify-center rounded-lg bg-white px-1.5 text-center text-sm opacity-0 shadow-sm group-hover:opacity-100"
                >
                    <span class="m-auto">
                        <x-noerd::icons.pencil class="h-3! w-3!" />
                    </span>
                </span>
            @endif
        </div>
    @elseif ($columnValue === 'selectAction')
        <div class="m-0.5 flex" @click.stop>
            <x-noerd::button
                type="button"
                icon="plus-circle"
                class="ml-auto"
                wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
            >
                {{ __($label) }}
            </x-noerd::button>
        </div>
    @elseif ($columnValue === 'deleteAction')
        <div class="m-0.5 flex">
            <x-noerd::button
                variant="danger"
                class="ml-auto"
                wire:confirm="{{ __('Are you sure you want to delete this entry?') }}"
                wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
            >
                {{ __($label) }}
            </x-noerd::button>
        </div>
    @elseif ($columnValue === 'secondAction')
        <div class="m-0.5 flex">
            <x-noerd::button
                variant="secondary"
                class="ml-auto"
                wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
            >
                {{ __($label) }}
            </x-noerd::button>
        </div>
    @elseif ($cellRendererView = \Noerd\Support\ListCellRenderer::viewFor($columnConfig ?? []))
        {{-- A registered `cell:` renderer draws the content; row click,
             alignment and padding stay the core's. --}}
        <div
            wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
            @class([
                'flex cursor-pointer items-center px-1.5 py-0.5',
                'justify-end' => ($columnConfig['align'] ?? 'left') === 'right',
                'justify-center' => ($columnConfig['align'] ?? 'left') === 'center',
            ])
        >
            @include($cellRendererView, \Noerd\Support\ListCellRenderer::data($value, $rowData ?? [], $columnConfig ?? []))
        </div>
    @else
        @if ($type === 'bool' || $type === 'boolean')
            @if (\Noerd\Support\ListCellFormatter::truthy($value))
                <div class="shrink-0 px-3 text-right">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="h-6 w-6 text-green-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </div>
            @else
                <div class="shrink-0 px-3 text-right">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="h-6 w-6 text-red-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </div>
            @endif
        @elseif ($type === 'inversebool')
            @if (\Noerd\Support\ListCellFormatter::truthy($value))
                <div class="shrink-0 px-3 text-right">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="h-6 w-6 text-green-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </div>
            @endif
        @else
            @if ($type === 'id')
                <div class="bg-gray-100" wire:click.stop.prevent="{{ $action }}('{{ $id }}')">
                    <input
                        type="text"
                        readonly
                        id="cell-{{ $cellId }}"
                        class="cursor-pointer underline w-auto border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent text-sm py-0.5 px-1.5 @if(in_array($type, ['number'])) text-right @endif"
                        value="{{ $value }}"
                    />
                </div>
            @elseif ($type === 'date')
                {{-- Rendered as text, not as <input type="date">: the browser would
                     localise a date input by ITS locale, the list follows the user's. --}}
                @if ($value)
                    <span
                        wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                        id="cell-{{ $cellId }}"
                        class="cursor-pointer px-1.5 py-0.5 text-sm"
                    >
                        {{ \Noerd\Support\ListCellFormatter::format($value, $formatColumn) }}
                    </span>
                @endif
            @elseif ($type === 'datetime')
                @if ($value)
                    <span
                        wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                        class="cursor-pointer px-1.5 py-0.5 text-sm"
                    >
                        {{ \Noerd\Support\ListCellFormatter::format($value, $formatColumn) }}
                    </span>
                @endif
            @elseif ($type === 'number')
                <input
                    type="text"
                    wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    readonly
                    id="cell-{{ $cellId }}"
                    class="cursor-pointer border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! active:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5 text-right"
                    value="{{ \Noerd\Support\ListCellFormatter::format($value, $formatColumn) }}"
                />
            @elseif ($type === 'currency')
                <input
                    type="text"
                    wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    readonly
                    id="cell-{{ $cellId }}"
                    class="w-full cursor-pointer border-1! border-transparent! bg-transparent p-0 px-1.5 py-0.5 text-right text-sm ring-0! focus:border-1! focus:ring-0! active:border-1!"
                    value="{{ \Noerd\Support\ListCellFormatter::format($value, $formatColumn) }}"
                />
            @elseif ($type === 'badge' || $type === 'relationBadge')
                {{-- The option label of a picklist value, or the title of a foreign key:
                     resolved by the formatter, drawn by the one badge component. --}}
                @php
                    $badgeText = \Noerd\Support\ListCellFormatter::format($value, $formatColumn);
                @endphp
                <div
                    wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    class="flex cursor-pointer items-center px-1.5"
                >
                    @if ($badgeText !== '')
                        <x-noerd::badge>{{ $badgeText }}</x-noerd::badge>
                    @endif
                </div>
            @elseif ($type === 'customAttribute')
                <div wire:click.stop.prevent="{{ $action }}('{{ $id }}')" class="flex cursor-pointer items-center">
                    <span class="w-full px-1.5 py-0.5 text-sm">{{ \Noerd\Support\ListCellFormatter::format(data_get($rowData, $columnValue), $formatColumn) }}</span>
                </div>
            @elseif ($type === 'checkbox')
                @php
                    // Editable checkbox cell bound to a component property:
                    // `wireModel` names the array property, the row id is the key,
                    // an optional `wireModelField` addresses a sub-key
                    // (e.g. permissions.{id}.read). `live: true` syncs per click.
                    // A row that does not carry the column's field at all opts
                    // out — the checkbox is "not applicable" there and renders
                    // nothing (e.g. a mode toggle only some row kinds offer).
                    $checkboxModel = ($columnConfig['wireModel'] ?? '')
                        . '.' . $id
                        . (isset($columnConfig['wireModelField']) ? '.' . $columnConfig['wireModelField'] : '');
                @endphp
                @if (array_key_exists($columnValue, (array) ($rowData ?? [])))
                    <div class="flex items-center justify-center px-1.5" @click.stop>
                        <input
                            type="checkbox"
                            @if ($columnConfig['live'] ?? false)
                                wire:model.live="{{ $checkboxModel }}"
                            @else
                                wire:model="{{ $checkboxModel }}"
                            @endif
                            class="text-brand-primary focus:ring-brand-border h-4 w-4 cursor-pointer rounded border-gray-300"
                        />
                    </div>
                @endif
            @elseif ($type === 'badge_with_text')
                @php
                    $badgeWithText = \Noerd\Support\ListCellFormatter::badgeWithText($value);
                @endphp
                <div
                    wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    class="flex cursor-pointer items-center gap-2 px-1.5 py-0.5"
                >
                    @if ($badgeWithText['badge'] !== '')
                        <x-noerd::badge :variant="$badgeWithText['variant']">{{ $badgeWithText['badge'] }}</x-noerd::badge>
                    @endif
                    @if ($badgeWithText['text'] !== '')
                        <span class="text-sm text-gray-600">{{ $badgeWithText['text'] }}</span>
                    @endif
                </div>
            @elseif ($type === 'relation_link')
                @php
                    $relationRoute = $columnConfig['route'] ?? null;
                    $relationRoute = $relationRoute && \Illuminate\Support\Facades\Route::has($relationRoute)
                        ? $relationRoute
                        : null;
                    // Both modes address the record via its $modelId property — the
                    // {modelId} route param and the component mount argument alike.
                    $relationIdParam = $columnConfig['idParam'] ?? 'modelId';
                    $relationId = $rowData[$columnConfig['idField'] ?? 'id'] ?? null;
                @endphp
                @if ($value && $relationRoute)
                    <button
                        @click.stop="$modalRoute({{ \Illuminate\Support\Js::from($relationRoute) }}, {{ \Illuminate\Support\Js::from([$relationIdParam => $relationId]) }}, null, null, null, {{ \Illuminate\Support\Js::from(array_filter(['fallbackComponent' => $columnConfig['modalComponent'] ?? null])) }})"
                        title="{{ $value }}"
                        class="ml-1.5 inline-flex max-w-[calc(100%-0.75rem)] min-w-0 cursor-pointer items-center rounded bg-brand-primary/10 px-2 py-1 text-xs font-medium text-brand-primary transition-colors hover:bg-brand-primary/20"
                    >
                        <span class="truncate">{{ $value }}</span>
                    </button>
                @elseif ($value && isset($columnConfig['modalComponent']))
                    <button
                        @click.stop="$modal({{ \Illuminate\Support\Js::from($columnConfig['modalComponent']) }}, {{ \Illuminate\Support\Js::from([$relationIdParam => $relationId]) }})"
                        title="{{ $value }}"
                        class="ml-1.5 inline-flex max-w-[calc(100%-0.75rem)] min-w-0 cursor-pointer items-center rounded bg-brand-primary/10 px-2 py-1 text-xs font-medium text-brand-primary transition-colors hover:bg-brand-primary/20"
                    >
                        <span class="truncate">{{ $value }}</span>
                    </button>
                @elseif ($value)
                    <span class="block truncate px-1.5 py-0.5 text-sm" title="{{ $value }}">{{ $value }}</span>
                @endif
            @elseif ($type === 'preview')
                @php
                    $cellPreview = \Noerd\Support\ListCellFormatter::preview($value);
                @endphp
                @if ($cellPreview)
                    <div class="px-1.5 py-0.5">
                        @include('noerd::components.list.preview', ['preview' => $cellPreview, 'previewSize' => 'cell'])
                    </div>
                @endif
            @elseif ($type === 'colored_text')
                @php
                    $coloredText = \Noerd\Support\ListCellFormatter::coloredText($value);
                @endphp
                <div
                    @if (isset($columnConfig['wireClick']))
                        wire:click.stop="{{ $columnConfig['wireClick'] }}({{ $rowData[$columnConfig['wireClickField'] ?? 'id'] ?? 'null' }})"
                    @else
                        wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    @endif
                    class="inline-flex max-w-full min-w-0 cursor-pointer items-center gap-1.5 px-1.5 py-0.5"
                >
                    @if ($coloredText['prefix'] !== '')
                        <span class="shrink-0 text-sm {{ $coloredText['prefixClass'] }}">{{ $coloredText['prefix'] }}</span>
                    @endif
                    @if ($coloredText['text'] !== '')
                        <span
                            title="{{ $coloredText['text'] }}"
                            @class([
                                'truncate text-sm',
                                'rounded px-2 font-medium' => $coloredText['styled'],
                                $coloredText['class'] => $coloredText['class'] !== '',
                            ])
                        >
                            {{ $coloredText['text'] }}
                        </span>
                    @endif
                    @if ($coloredText['icon'])
                        <x-icon
                            name="{{ $coloredText['icon'] }}"
                            class="h-4 w-4 shrink-0 {{ $coloredText['iconClass'] }}"
                        />
                    @endif
                </div>
            @else
                <input
                    {{-- Only a real text-like input type reaches the browser; an unknown column type renders as text. --}}
                    type="{{ in_array($type, ['text', 'email', 'tel', 'url'], true) ? $type : 'text' }}"
                    wire:click.stop.prevent="{{ $action }}('{{ $id }}')"
                    readonly
                    id="cell-{{ $cellId }}"
                    @if ($isTranslatableCell)
                        title="{{ __('This field is translatable. The value belongs to the language selected in the language switcher.') }}"
                    @endif
                    class="w-full cursor-pointer border-1! border-transparent! bg-transparent p-0 px-1.5 py-0.5 text-sm ring-0! focus:border-1! focus:ring-0! active:border-1!"
                    value="{{ $value }}"
                />

            @endif
        @endif
    @endif
</td>
