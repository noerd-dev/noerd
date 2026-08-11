@props(['title' => null, 'count' => null])

<div {{ $attributes->merge(['class' => 'h-full flex flex-col bg-white border border-gray-300 rounded-lg overflow-hidden']) }}>
    @if (isset($header) || $title !== null)
        <div class="shrink-0 border-b border-gray-200 px-4 py-3 text-sm">
            @isset($header)
                {{ $header }}
            @else
                <div class="flex items-center justify-between">
                    <div class="font-medium text-gray-700">{{ $title }}</div>
                    @if ($count !== null)
                        <div class="font-semibold">{{ $count }}</div>
                    @endif
                </div>
            @endisset
        </div>
    @endif

    <div class="min-h-0 flex-1 overflow-y-auto">{{ $slot }}</div>
</div>
