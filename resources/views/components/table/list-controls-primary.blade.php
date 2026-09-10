{{--
    The primary list buttons: every YAML action that is not `style: secondary`,
    rendered on the TITLE row, right-aligned, at every viewport width — the primary
    call to action of a list is never moved anywhere else. The registry list
    actions live in list-controls-registry.

    Expects: $host (the NoerdList Livewire component), $controls (see
    NoerdList::headerControls()), $listRelations.
--}}
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
                             shortcut and would widen the button on a phone. --}}
                        <kbd class="ml-2 hidden rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-brand-primary-text lg:inline-block">{{ $shortcut['badge'] }}</kbd>
                    @endif
                </x-noerd::button>
            </div>
        @endforeach
    </div>
@endif
