{{--
    A card of settings rows: every `<x-noerd::toggle>` inside renders as one padded row,
    separated by hairlines. An optional `title` and `description` head the card.

        <x-noerd::toggle-group :title="__('Checkout')">
            <x-noerd::toggle wire:model="…" :label="__('…')" />
            <x-noerd::toggle wire:model="…" :label="__('…')" />
        </x-noerd::toggle-group>
--}}
@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => '']) }}>
    @if ($title !== null || $description !== null)
        <div class="mb-3 px-1">
            @if ($title !== null)
                <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>
            @endif
            @if ($description !== null)
                <p class="mt-0.5 text-[13px] leading-5 text-gray-500">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{-- The rows are the direct children: padded and hovered here, so a toggle needs no context. --}}
    <div class="divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-xs ring-1 ring-gray-900/[0.07] *:px-4 *:py-4 *:transition-colors sm:*:px-5 *:hover:bg-gray-50/70 *:has-disabled:hover:bg-transparent">
        {{ $slot }}
    </div>
</section>
