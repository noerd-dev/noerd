@props(['layout' => null, 'modelId' => null])

@if($layout && isset($layout['tabs']) && count($layout['tabs']) > 0)
    <div class="py-6 w-full">
        <div class="border-b border-gray-300 flex w-full">
            <nav class="inline-block" aria-label="Tabs">
                @foreach($layout['tabs'] as $tab)
                    @php
                        $showTab = true;
                        if (isset($tab['requiresId']) && $tab['requiresId'] && !$modelId) {
                            $showTab = false;
                        }
                        if (isset($tab['permission'])) {
                            $permissionModel = $tab['permissionModel'] ?? null;
                            $showTab = $showTab && Gate::allows($tab['permission'], $permissionModel);
                        }
                    @endphp
                    @if($showTab)
                        @if(isset($tab['route']))
                            <x-noerd::tab :route="$tab['route']" :active="request()->routeIs($tab['route'])">
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @else
                            <x-noerd::tab :tabNumber="$tab['number']">
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @endif
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
