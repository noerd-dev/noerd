{{--
    The list header controls that always stay on the header row, next to the title:
    the registry list actions and every YAML action that is not `style: secondary`.
    These never move into the filter drawer — the primary call to action of a list
    has to remain reachable at every viewport width.

    Expects: $host (the NoerdList Livewire component), $controls (see
    NoerdList::headerControls()), $listRelations.
--}}
@if ($controls['registry'] !== [])
    {{-- Collapses when every action hid itself: the children are server-rendered
         before Alpine initializes, so probing for a button is reliable. Without
         this an empty wrapper would still carry the parent's gap. --}}
    <div
        x-data="{ hasActions: false }"
        x-init="hasActions = $el.querySelector('button') !== null"
        x-show="hasActions"
        x-cloak
        class="flex shrink-0 items-center gap-2"
    >
        @foreach ($controls['registry'] as $listHeaderAction)
            @livewire($listHeaderAction, [
                'model' => $host->listModel ?? null,
                'component' => $host->getComponentName(),
            ], key('list-header-action-' . $listHeaderAction))
        @endforeach
    </div>
@endif

@if ($controls['primary'] !== [])
    <div class="flex shrink-0 gap-2">
        @foreach ($controls['primary'] as $actionIndex => $actionItem)
            @php
                // $actionIndex is the position in the FULL YAML action list
                // (headerControls() preserves it), so pulling the secondary buttons
                // out keeps the shortcut the YAML assigned.
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
                @if ($shortcut !== null)
                    x-data
                    @keydown.window="let e = $event; if ({{ $shortcut['js'] }}) { e.preventDefault(); $refs.actionBtn{{ $actionIndex }}.click(); }"
                @endif
            >
                <x-noerd::button
                    variant="primary"
                    :icon="$actionItem['heroicon'] ?? 'plus'"
                    x-ref="actionBtn{{ $actionIndex }}"
                    class="relative h-8 whitespace-nowrap"
                    @click.prevent="{{ $clickExpression }}"
                >
                    {{ __($actionItem['label']) }}
                    @if ($shortcut !== null)
                        {{-- Hidden on touch widths: the badge only advertises a keyboard
                             shortcut and would widen the button on a phone, where the
                             header has to stay on a single row. --}}
                        <kbd class="ml-2 hidden rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-brand-primary-text lg:inline-block">{{ $shortcut['badge'] }}</kbd>
                    @endif
                </x-noerd::button>
            </div>
        @endforeach
    </div>
@endif
