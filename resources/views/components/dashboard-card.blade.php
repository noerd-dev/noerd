<a
    @isset($external) href="{{ $external }}" target="_blank" @else href="#/" @endisset
    @isset($component)
        @click="$modal('{{ $component }}', {{ json_encode($arguments ?? []) }})"
    @endisset
    class="{{ $background ?? 'bg-white' }} border border-gray-300  hover:bg-gray-50 w-36 h-36 mr-6 mt-6 flex p-2 py-4 text-sm text-center rounded-lg items-center justify-center"
>
    <div class="m-auto">
        <div class="inline-block">
            @isset($icon)
                <img alt="" src="/assets/svg/{{ $icon }}.svg" class="mb-2 h-6 w-6" />
            @endisset
            @isset($heroicon)
                <x-icon name="{{ $heroicon }}" class="mb-2 h-6 w-6 text-gray-800" />
            @endisset
        </div>

        <div class="w-full text-gray-500">{{ $title }}</div>

        @isset($value)
            <div class="text-2xl font-semibold">{{ number_format($value, 0, ',', '.') }}</div>
        @endisset
    </div>
</a>
