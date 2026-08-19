<!-- Framework File -->
{{-- Question-mark affordance rendered next to a field label when the YAML field
     declares `helpText:`. The panel is teleported to <body> because detail modals
     apply persistent transforms (which would make them the containing block for a
     fixed panel) and the compact/numbered labels use `truncate` (overflow: hidden)
     — both would clip an in-place panel. --}}
@props(['text' => null, 'icon' => 'question-mark-circle', 'iconClass' => 'text-gray-400 hover:text-gray-600'])

@php
    $helpTooltipText = mb_trim((string) ($text ?? ''));
@endphp

@if ($helpTooltipText !== '')
    <span x-data="{ open: false }" @keydown.escape.window="open = false" class="inline-flex align-middle">
        {{-- type=button + prevent/stop: the icon sits inside <label for="…">, so a
             forwarded click would activate the control (and toggle a checkbox). --}}
        <button
            type="button"
            x-ref="helpTrigger"
            @mouseenter="open = true"
            @mouseleave="open = false"
            @focus="open = true"
            @blur="open = false"
            @click.prevent.stop="open = ! open"
            @click.outside="open = false"
            :aria-expanded="open"
            class="focus-visible:ring-brand-border ms-1 inline-flex shrink-0 cursor-help rounded-full align-middle focus:outline-none focus-visible:ring-2 {{ $iconClass }}"
        >
            <x-icon :name="$icon" class="size-4" />
            <span class="sr-only">{{ $helpTooltipText }}</span>
        </button>

        <template x-teleport="body">
            <div
                role="tooltip"
                aria-hidden="true"
                x-show="open"
                x-transition.opacity.duration.100ms
                x-anchor.top.offset.6="$refs.helpTrigger"
                style="display: none"
                class="z-[100] max-w-xs rounded-md bg-gray-900 px-2.5 py-1.5 text-xs leading-snug font-normal whitespace-normal text-white shadow-lg"
            >
                {{ $helpTooltipText }}
            </div>
        </template>
    </span>
@endif
