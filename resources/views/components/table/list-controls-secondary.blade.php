{{--
    The list's secondary buttons: CSV export and every YAML action marked
    `style: secondary`. They sit on the TITLE row next to the primary buttons —
    the title row holds every button, the filter row below holds search, filters
    and pagination.

    Expects: $host (the NoerdList Livewire component), $controls (see
    NoerdList::headerControls()), $listRelations.
--}}
@if ($controls['csv'])
    <x-noerd::button
        wire:key="list-csv-export"
        variant="secondary"
        icon="arrow-down-tray"
        class="h-8 shrink-0"
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
        // (route:) or calls a method on the list component (action:). The
        // method name is interpolated into an Alpine expression, so only
        // identifier characters of the YAML value survive.
        $actionMethod = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($actionItem['action'] ?? ''));
        $clickExpression = isset($actionItem['route'])
            ? '$modalRoute(' . Js::from($actionItem['route']) . ', ' . Js::from($actionItem['arguments'] ?? []) . ')'
            : '$wire.' . $actionMethod . '(null, ' . Js::from($listRelations ?? []) . ')';
    @endphp
    <div
        wire:key="list-secondary-action-{{ $actionIndex }}"
        class="shrink-0"
        @if ($shortcut !== null)
            x-data
            @keydown.window="let e = $event; if ((window.noerdTopLayer?.($el) ?? true) && ({{ $shortcut['js'] }})) { e.preventDefault(); $refs.actionBtn{{ $actionIndex }}.click(); }"
        @endif
    >
        <x-noerd::button
            variant="secondary"
            :icon="$actionItem['heroicon'] ?? null"
            x-ref="actionBtn{{ $actionIndex }}"
            class="relative h-8 whitespace-nowrap"
            @click.prevent="{{ $clickExpression }}"
        >
            {{ __($actionItem['label']) }}
            @if ($shortcut !== null)
                <kbd class="ml-2 hidden rounded border border-gray-300 bg-gray-100 px-1 py-0.5 text-xs text-gray-500 lg:inline-block">{{ $shortcut['badge'] }}</kbd>
            @endif
        </x-noerd::button>
    </div>
@endforeach
