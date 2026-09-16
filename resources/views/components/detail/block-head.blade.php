@php
    // Also included by hand-built chrome (detail-list, positions/section) and used as
    // <x-noerd::detail.block-head> — the action keys are optional everywhere.
    $headerActions ??= [];
    $headerActionHost ??= null;
    // A component's own buttons for this head row (the `blockActions` slot of
    // x-noerd::tab-content, or a view()/HtmlString passed by a direct block include).
    $blockActions ??= null;
    $blockActionsHtml = $blockActions instanceof \Illuminate\Contracts\Support\Htmlable
        ? $blockActions->toHtml()
        : (string) ($blockActions ?? '');
    $hasBlockActions = trim($blockActionsHtml) !== '';
    $title = (string) ($title ?? '');
    $description = (string) ($description ?? '');
@endphp
<div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($title !== '')
            <div class="pb-2 text-sm leading-6 font-semibold text-gray-900">{{ $title }}</div>
        @endif
        @if ($description !== '')
            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>
    @if ($hasBlockActions || ($headerActions !== [] && $headerActionHost !== null))
        {{-- The action row of the form block: the component's own `blockActions`
             first, then the module-contributed detail header actions
             (HeaderActionsRegistry) — top-right of the block, not in the modal header.
             Grouped so the icon buttons keep the 8px rhythm; collapses when every
             action hid itself — otherwise the empty wrapper would still take the gap.
             pt-1: the block head sits flush against the top edge of the hosting
             scroll container (x-noerd::tab-panel clips there), so without the gap
             a button's focus ring is cut off. --}}
        <div
            x-data="{ hasActions: false }"
            x-init="hasActions = $el.querySelector('button, a') !== null"
            x-show="hasActions"
            x-cloak
            class="flex shrink-0 items-center gap-2 pt-1"
        >
            {!! $blockActionsHtml !!}
            @if ($headerActionHost !== null)
                @foreach ($headerActions as $headerAction)
                    @livewire($headerAction, [
                        'model' => $headerActionHost->detailModel ?? null,
                        'component' => $headerActionHost->getName(),
                    ], key('detail-header-action-' . $headerAction))
                @endforeach
            @endif
        </div>
    @endif
</div>
