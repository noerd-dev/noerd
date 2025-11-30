@props(['layout' => null, 'modelId' => null])

@if($layout && isset($layout['tabs']) && count($layout['tabs']) > 0)
    <div class="py-6 w-full">
        <div class="border-b border-gray-300 flex w-full">
            <nav class="inline-block" aria-label="Tabs">
                @foreach($layout['tabs'] as $tab)
                    @if(!($tab['requiresId'] ?? false) || $modelId)
                        <x-noerd::tab :tabNumber="$tab['number']">
                            {{ __($tab['label']) }}
                        </x-noerd::tab>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
@elseif(!$slot->isEmpty())
    <div class="py-6 w-full">
        <div class="border-b border-gray-300 flex w-full">
            <nav class="inline-block" aria-label="Tabs">
                {{ $slot }}
            </nav>
        </div>
    </div>
@endif
