@props(['layout' => null, 'modelId' => null])

@php
    /**
     * Resolve argument values from YML configuration.
     * Supports:
     * - '$variableName' - references a Livewire component property
     * - '$modelId' - references the modelId passed to this component
     * - Static values - passed through as-is
     */
    $resolveArguments = function(array $arguments) use ($modelId) {
        $resolved = [];
        foreach ($arguments as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$')) {
                $varName = substr($value, 1);
                if ($varName === 'modelId') {
                    $resolved[$key] = $modelId;
                } elseif (isset($this) && property_exists($this, $varName)) {
                    $resolved[$key] = $this->$varName;
                } else {
                    $resolved[$key] = null;
                }
            } else {
                $resolved[$key] = $value;
            }
        }
        return $resolved;
    };
@endphp

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
                        if (isset($tab['viewExists']) && !View::exists($tab['viewExists'])) {
                            $showTab = false;
                        }
                    @endphp
                    @if($showTab)
                        @if(isset($tab['route']))
                            <x-noerd::tab :route="$tab['route']" :active="request()->routeIs($tab['route'])">
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @elseif(isset($tab['component']))
                            @php
                                $tabArguments = isset($tab['arguments']) ? $resolveArguments($tab['arguments']) : [];
                            @endphp
                            <x-noerd::tab :component="$tab['component']" :arguments="$tabArguments">
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
