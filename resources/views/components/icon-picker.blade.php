<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\IconHelper;

new class extends Component {
    #[Locked]
    public string $context = '';

    public string $search = '';

    /**
     * Filtered server-side: the full heroicon set is ~600 entries, and
     * rendering every one of them only to hide it with x-show made the modal
     * heavy on every open.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function filteredIcons(): array
    {
        $icons = IconHelper::heroicons();
        $needle = str_replace(' ', '-', mb_strtolower(mb_trim($this->search)));

        if ($needle === '') {
            return $icons;
        }

        return array_values(array_filter($icons, fn(string $name): bool => str_contains($name, $needle)));
    }

    public function mount(string $context = ''): void
    {
        $this->context = $context;
    }

    public function selectIcon(string $name): void
    {
        abort_unless(in_array($name, IconHelper::heroicons(), true), 422);

        $this->dispatch('setFieldValue', field: $this->context, value: $name);
        $this->dispatch('closeTopModal');
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Select Icon') }}</x-noerd::modal-title>
    </x-slot:header>

    <div class="py-6">
        <x-noerd::text-input
            type="text"
            wire:model.live.debounce.300ms="search"
            autofocus
            placeholder="{{ __('Search icons') }}"
            class="mt-0"
        />

        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mt-4">
            @foreach($this->filteredIcons as $name)
                <button
                    type="button"
                    wire:key="icon-{{ $name }}"
                    title="{{ $name }}"
                    wire:click="selectIcon({{ \Illuminate\Support\Js::from($name) }})"
                    class="flex flex-col items-center justify-center gap-1 p-2 rounded-lg border border-transparent hover:bg-gray-50 hover:border-gray-300 cursor-pointer"
                >
                    <x-icon name="{{ $name }}" class="w-6 h-6 text-gray-700"/>
                    <span class="w-full truncate text-center text-[10px] leading-tight text-gray-500">{{ $name }}</span>
                </button>
            @endforeach
        </div>
    </div>
</x-noerd::page>
