{{--
    Generic list header controls: CSV export, search, registry list actions and
    the YAML `actions` buttons. Included by x-noerd::modal-title for every
    NoerdList host — whether the title came from the generic list-header or
    from a component's own custom header slot. Expects:
    $host (the NoerdList Livewire component), $listSettings, $objectAccessDenied,
    $listRelations. Positioning (ml-auto, modal controls offset) is owned by the
    modal-title wrapper — never add offsets here.
--}}
@php
    $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
    $showCsvExport = $host->enableCsvExport ?? false;
    $isPicker = $host->returnsSelection ?? false;
    // A read-denied list keeps its header but loses the data affordances; the
    // YAML actions were already stripped by buildList().
    $searchEnabled = ! ($listSettings['disableSearch'] ?? false) && ! $objectAccessDenied;
    $listYamlActions = $isPicker ? [] : ($listSettings['actions'] ?? []);
    $listRegistryActions = $isPicker ? [] : app(\Noerd\Services\HeaderActionsRegistry::class)->listActions();
@endphp

@if ($searchEnabled || $showCsvExport)
    <div class="flex items-center gap-2">
        @if ($showCsvExport)
            <x-noerd::button
                variant="secondary"
                icon="arrow-down-tray"
                class="h-8"
                title="{{ __('Export CSV') }}"
                wire:click="exportCsv"
            >
                CSV
            </x-noerd::button>
        @endif
        @if ($searchEnabled)
            <div
                x-data="{ searchFocused: false }"
                @keydown.window="let e = $event; if ({{ $searchShortcut['js'] }}) { e.preventDefault(); $refs.searchInput.focus(); }"
            >
                <div class="relative">
                    <x-noerd::text-input
                        x-ref="searchInput"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                        @keydown.escape="$refs.searchInput.blur()"
                        placeholder="{{ __('Search') }}"
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        class="!mt-0 mb-3 h-8 min-w-[200px] pr-8 lg:mb-0"
                    />
                    <kbd
                        x-show="! searchFocused"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="pointer-events-none absolute top-1/2 right-1.5 -translate-y-1/2 rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500"
                    >{{ $searchShortcut['badge'] }}</kbd>
                </div>
            </div>
        @endif
    </div>
@endif

@if ($listRegistryActions !== [])
    {{-- Collapses when every action hid itself: the children are
         server-rendered before Alpine initializes, so probing for a
         button is reliable. Without this an empty wrapper would still
         carry the parent's gap. --}}
    <div
        x-data="{ hasActions: false }"
        x-init="hasActions = $el.querySelector('button') !== null"
        x-show="hasActions"
        x-cloak
        class="flex shrink-0 items-center gap-2"
    >
        @foreach ($listRegistryActions as $listHeaderAction)
            @livewire($listHeaderAction, [
                'model' => $host->listModel ?? null,
                'component' => $host->getComponentName(),
            ], key('list-header-action-' . $listHeaderAction))
        @endforeach
    </div>
@endif

@if ($listYamlActions !== [])
    <div class="flex gap-2">
        @foreach ($listYamlActions as $actionIndex => $actionItem)
            @php
                $isSecondary = ($actionItem['style'] ?? '') === 'secondary';
                $effectiveShortcut = $actionItem['shortcut']
                    ?? ($actionIndex === 0 ? 'n' : null);
                $hasShortcut = $effectiveShortcut !== null;
                $shortcut = $hasShortcut
                    ? \Noerd\Helpers\KeyboardShortcutHelper::parse('action_' . ($actionItem['action'] ?? $actionItem['route'] ?? ''), $effectiveShortcut)
                    : null;
                // An action either opens a named Livewire route as a modal
                // (route:) or calls a method on the list component (action:).
                $clickExpression = isset($actionItem['route'])
                    ? '$modalRoute(' . Js::from($actionItem['route']) . ', ' . Js::from($actionItem['arguments'] ?? []) . ')'
                    : '$wire.' . $actionItem['action'] . '(null, ' . Js::from($listRelations ?? []) . ')';
            @endphp
            @if ($hasShortcut)
                <div
                    x-data
                    @keydown.window="let e = $event; if ({{ $shortcut['js'] }}) { e.preventDefault(); $refs.actionBtn{{ $actionIndex }}.click(); }"
                >
            @else
                <div>
            @endif
            <x-noerd::button
                :variant="$isSecondary ? 'secondary' : 'primary'"
                :icon="$actionItem['heroicon'] ?? ($isSecondary ? null : 'plus')"
                x-ref="actionBtn{{ $actionIndex }}"
                class="relative h-8"
                @click.prevent="{{ $clickExpression }}"
            >
                {{ __($actionItem['label']) }}
                @if ($hasShortcut)
                    <kbd
                        @class([
                            'ml-2 rounded px-1 py-0.5 text-xs',
                            'border border-gray-300 bg-gray-100 text-gray-500' => $isSecondary,
                            'border border-white/30 bg-white/20 text-brand-primary-text' => !$isSecondary,
                        ])
                    >{{ $shortcut['badge'] }}</kbd>
                @endif
            </x-noerd::button>
            </div>
        @endforeach
    </div>
@endif
