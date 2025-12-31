@props(['buttons' => []])

<div class="flex items-center gap-1 border-b border-gray-200 py-2 px-1">
    @foreach($buttons as $button)
        @if(($button['type'] ?? '') === 'separator')
            <div class="h-6 w-px bg-gray-300 mx-2"></div>
        @else
            <button
                type="button"
                wire:click="{{ $button['action'] }}"
                @isset($button['confirm']) wire:confirm="{{ __($button['confirm']) }}" @endisset
                wire:loading.attr="disabled"
                wire:target="{{ $button['action'] }}"
                class="inline-flex items-center gap-1.5 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded"
            >
                @isset($button['heroicon'])
                    <x-icon name="{{ $button['heroicon'] }}" class="w-4 h-4"/>
                @endisset
                <span wire:loading.remove wire:target="{{ $button['action'] }}">
                    {{ __($button['label']) }}
                </span>
                @isset($button['loading'])
                    <span wire:loading wire:target="{{ $button['action'] }}">
                        {{ __($button['loading']) }}
                    </span>
                @endisset
            </button>
        @endif
    @endforeach
</div>
