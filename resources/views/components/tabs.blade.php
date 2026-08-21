@props(['layout' => null, 'modelId' => null])

@php
    /**
     * A list host's page body carries no chrome padding — the list brings its own
     * spacing (see x-noerd::page). A tab bar sitting ABOVE that list is the body's
     * first child though, so it would end up flush against the page header: it
     * supplies the missing gap itself, matching the 24px a detail page gets from
     * the chrome. Compact/embedded lists have no page header to sit under.
     */
    $tabsTopPadding = isset($__livewire)
        && in_array(\Noerd\Traits\NoerdList::class, class_uses_recursive($__livewire), true)
        && ! ($__livewire->compact ?? false)
        && ! ($__livewire->minimal ?? false)
            ? ' pt-6'
            : '';

    /**
     * Resolve argument values from YML configuration.
     * Supports:
     * - '$variableName' - references a Livewire component property
     * - '$modelId' - references the modelId passed to this component
     * - Static values - passed through as-is
     */
    $resolveArguments = function (array $arguments) use ($modelId) {
        $resolved = [];
        foreach ($arguments as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$')) {
                $varName = mb_substr($value, 1);
                if ($varName === 'modelId') {
                    $resolved[$key] = $modelId;
                } elseif (isset($this) && property_exists($this, $varName)) {
                    $resolved[$key] = $this->{$varName};
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

@if ($layout && isset($layout['tabs']) && count($layout['tabs']) > 0)
    <div class="w-full shrink-0 pb-6{{ $tabsTopPadding }}">
        <div class="flex w-full border-b border-gray-300">
            <nav class="inline-block" aria-label="Tabs">
                @foreach ($layout['tabs'] as $tab)
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

                        // Reactive client-side visibility — mirrors field-level showIf
                        // (Alpine x-show against a Livewire property).
                        $tabShowIf = '';
                        if (isset($tab['showIf'])) {
                            if (is_string($tab['showIf'])) {
                                $tabShowIf = 'x-show="$wire.' . $tab['showIf'] . '"';
                            } elseif (is_array($tab['showIf'])) {
                                $tabShowIf = 'x-show="$wire.' . $tab['showIf']['field'] . " === '" . $tab['showIf']['value'] . "'\"";
                            }
                        }
                    @endphp
                    @if ($showTab)
                        @if ($tabShowIf)
                            <div class="inline-flex" {!! $tabShowIf !!}>
                        @endif
                        @if (isset($tab['modalRoute']))
                            @php
                                $tabArguments = isset($tab['arguments']) ? $resolveArguments($tab['arguments']) : [];
                                // The record's own route supplies the href, so cmd-click
                                // and "open in new tab" land on the real full page.
                                $tabRouteParameters = isset($tab['routeParameters'])
                                    ? $resolveArguments($tab['routeParameters'])
                                    : array_intersect_key($tabArguments, ['modelId' => null]);
                            @endphp
                            <x-noerd::tab
                                :modalRoute="$tab['modalRoute']"
                                :component="$tab['component'] ?? null"
                                :arguments="$tabArguments"
                                :routeParameters="$tabRouteParameters"
                            >
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @elseif (isset($tab['component']))
                            @php
                                $tabArguments = isset($tab['arguments']) ? $resolveArguments($tab['arguments']) : [];
                                $isRoutable = ! empty($tab['routable']);
                                $tabRoute = $isRoutable ? 'component-page' : null;
                                $tabRouteParameters = $isRoutable
                                    ? array_merge(['componentName' => $tab['component']], $tabArguments)
                                    : [];
                            @endphp
                            <x-noerd::tab
                                :component="$tab['component']"
                                :arguments="$tabArguments"
                                :route="$tabRoute"
                                :routeParameters="$tabRouteParameters"
                            >
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @elseif (isset($tab['route']))
                            <x-noerd::tab :route="$tab['route']" :active="request()->routeIs($tab['route'])">
                                {{ __($tab['label']) }}
                            </x-noerd::tab>
                        @else
                            <x-noerd::tab :tabNumber="$tab['number']"> {{ __($tab['label']) }} </x-noerd::tab>
                        @endif
                        @if ($tabShowIf) </div>@endif
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
@elseif (!$slot->isEmpty())
    <div class="w-full shrink-0 pb-6{{ $tabsTopPadding }}">
        <div class="flex w-full border-b border-gray-300">
            <nav class="inline-block" aria-label="Tabs">{{ $slot }}</nav>
        </div>
    </div>
@endif
