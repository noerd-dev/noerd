@props([
    'attributes' => null,
    'showDelete' => false,
    'showSave' => true,
    'deleteMessage' => null,
])

<div
    {{ $attributes->merge(['class' => 'ml-auto']) }}
    x-data="{showButtons: false}">

    @php
        $deleteBadge = \Noerd\Helpers\KeyboardShortcutHelper::toBadge('delete', 'ctrl+backspace');
        $saveBadge = \Noerd\Helpers\KeyboardShortcutHelper::toBadge('save', 'ctrl+enter');
    @endphp

    <div class="ml-auto flex gap-2">
        <div x-show="$wire.showSuccessIndicator"
             x-transition.out.opacity.duration.1000ms
             x-noerd::effect="if($wire.showSuccessIndicator) setTimeout(() => $wire.showSuccessIndicator = false, 3000)"
             class="flex mt-2 mr-2">
            <div class="flex ml-auto">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{__('Successfully saved')}}
                    </p>
                </div>
            </div>
        </div>

        @if($deleteMessage)
            <x-noerd::buttons.delete wire:key="{{\Illuminate\Support\Str::uuid()}}"
                              wire:click="delete"
                              x-show="showButtons"
                              wire:confirm="{{$deleteMessage}}"
                              @click="show= false">
                {{ __('Delete') }}
            </x-noerd::buttons.delete>
        @else
            <x-noerd::buttons.delete wire:key="{{\Illuminate\Support\Str::uuid()}}"
                              wire:click="delete"
                              x-show="showButtons"
                              @click="show= false">
                {{ __('Delete') }}
            </x-noerd::buttons.delete>
        @endif

        <x-noerd::buttons.cancel x-show="showButtons" @click="showButtons = false">
            {{__('Cancel')}}
        </x-noerd::buttons.cancel>

        @if($showDelete)
            <x-noerd::buttons.delete wire:key="{{\Illuminate\Support\Str::uuid()}}" x-show="!showButtons"
                              @click="showButtons = true">
                {{ __('Delete') }}
                <kbd class="ml-2 rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-white">{{ $deleteBadge }}</kbd>
            </x-noerd::buttons.delete>
        @endif

        @if($showSave)
            <x-noerd::store-button wire:click="store">
                {{__('Save')}}
                <kbd class="ml-2 rounded border border-white/30 bg-white/20 px-1 py-0.5 text-xs text-white">{{ $saveBadge }}</kbd>
            </x-noerd::store-button>
        @endif
    </div>

</div>
