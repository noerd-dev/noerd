{{--
    The list header's secondary buttons: CSV export and every YAML action marked
    `style: secondary`. Rendered ONCE — below `xl` they stack full-width in the
    filter drawer, from `xl` on they sit on the header row. The layout switch is
    pure CSS (`max-xl:` / `xl:`), so there is no second copy and therefore no key
    prefix, no `stacked` flag and no shortcut that could fire twice.

    Expects: $host (the NoerdList Livewire component), $controls (see
    NoerdList::headerControls()), $listRelations.
--}}
@if ($controls['csv'])
    <x-noerd::button
        wire:key="list-csv-export"
        variant="secondary"
        icon="arrow-down-tray"
        {{-- x-noerd::button centres itself with `my-auto` for the header ROW; in the
             drawer's flex COLUMN that margin would absorb the free vertical space
             and scatter the buttons down the panel. --}}
        class="max-xl:!my-0 max-xl:w-full xl:h-8 xl:shrink-0"
        title="{{ __('Export CSV') }}"
        wire:click="exportCsv"
    >
        CSV
    </x-noerd::button>
@endif

@foreach ($controls['secondary'] as $actionIndex => $actionItem)
    @php
        // $actionIndex is the position in the FULL YAML action list (headerControls()
        // preserves it), so splitting primary from secondary keeps the shortcut the
        // YAML assigned.
        $effectiveShortcut = $actionItem['shortcut'] ?? ($actionIndex === 0 ? 'n' : null);
        $shortcut = $effectiveShortcut !== null
            ? \Noerd\Helpers\KeyboardShortcutHelper::parse('action_' . ($actionItem['action'] ?? $actionItem['route'] ?? ''), $effectiveShortcut)
            : null;
        // An action either opens a named Livewire route as a modal
        // (route:) or calls a method on the list component (action:).
        $clickExpression = isset($actionItem['route'])
            ? '$modalRoute(' . Js::from($actionItem['route']) . ', ' . Js::from($actionItem['arguments'] ?? []) . ')'
            : '$wire.' . $actionItem['action'] . '(null, ' . Js::from($listRelations ?? []) . ')';
    @endphp
    <div
        wire:key="list-secondary-action-{{ $actionIndex }}"
        class="max-xl:w-full xl:shrink-0"
        @if ($shortcut !== null)
            x-data
            @keydown.window="let e = $event; if ({{ $shortcut['js'] }}) { e.preventDefault(); $refs.actionBtn{{ $actionIndex }}.click(); }"
        @endif
    >
        <x-noerd::button
            variant="secondary"
            :icon="$actionItem['heroicon'] ?? null"
            x-ref="actionBtn{{ $actionIndex }}"
            class="relative max-xl:!my-0 max-xl:w-full xl:h-8"
            @click.prevent="{{ $clickExpression }}"
        >
            {{ __($actionItem['label']) }}
            @if ($shortcut !== null)
                <kbd class="ml-2 hidden rounded border border-gray-300 bg-gray-100 px-1 py-0.5 text-xs text-gray-500 xl:inline-block">{{ $shortcut['badge'] }}</kbd>
            @endif
        </x-noerd::button>
    </div>
@endforeach
