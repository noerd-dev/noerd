@props(['buttons' => []])

<div class="flex flex-wrap items-center gap-3 pb-3">
    @foreach($buttons as $button)
        @php
            $type = $button['type'] ?? '';
            $isDisabled = ! empty($button['disabled']);
            $action = $button['action'] ?? null;
            $variantClasses = match($button['variant'] ?? 'neutral') {
                'success' => 'bg-green-100 text-green-800',
                'warning' => 'bg-yellow-100 text-yellow-800',
                default => 'bg-gray-100 text-gray-600',
            };
            $buttonClasses = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded';
            $buttonClasses .= $isDisabled ? ' cursor-not-allowed opacity-50' : ' cursor-pointer hover:bg-gray-50';
        @endphp

        @if($type === 'separator')
            <div class="h-6 w-px bg-gray-300 mx-2"></div>
        @elseif($type === 'status')
            <span class="inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-sm font-medium {{ $variantClasses }}">
                @isset($button['heroicon'])
                    <x-icon name="{{ $button['heroicon'] }}" class="w-4 h-4"/>
                @endisset
                {{ __($button['label']) }}
            </span>
        @else
            <button
                type="button"
                @if(! $isDisabled && $action) wire:click="{{ $action }}" @endif
                @isset($button['confirm']) wire:confirm="{{ __($button['confirm']) }}" @endisset
                @if($isDisabled) disabled @elseif($action) wire:loading.attr="disabled" wire:target="{{ $action }}" @endif
                class="{{ $buttonClasses }}"
            >
                @isset($button['heroicon'])
                    <x-icon name="{{ $button['heroicon'] }}" class="w-4 h-4"/>
                @endisset
                <span @if($action) wire:loading.remove wire:target="{{ $action }}" @endif>
                    {{ __($button['label']) }}
                </span>
                @isset($button['loading'])
                    <span wire:loading wire:target="{{ $action }}">
                        {{ __($button['loading']) }}
                    </span>
                @endisset
            </button>
        @endif
    @endforeach
</div>
