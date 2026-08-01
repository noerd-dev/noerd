<!-- Framework File -->
@php
    $getShowIfDirective = function($field): string {
        $directive = '';

        // showIf - positive condition
        if (isset($field['showIf'])) {
            if (is_string($field['showIf'])) {
                $directive = 'x-show="$wire.' . $field['showIf'] . '"';
            } elseif (is_array($field['showIf'])) {
                $checkField = $field['showIf']['field'];
                $checkValue = $field['showIf']['value'];
                $directive = "x-show=\"\$wire.{$checkField} === '{$checkValue}'\"";
            }
        }

        // showIfNot - negated condition
        if (isset($field['showIfNot'])) {
            if (is_string($field['showIfNot'])) {
                $directive = 'x-show="!$wire.' . $field['showIfNot'] . '"';
            } elseif (is_array($field['showIfNot'])) {
                $checkField = $field['showIfNot']['field'];
                $checkValue = $field['showIfNot']['value'];
                $directive = "x-show=\"\$wire.{$checkField} !== '{$checkValue}'\"";
            }
        }

        return $directive;
    };

    $fieldTypeRegistry = app(\Noerd\Services\FieldTypeRegistry::class);
    $themeRegistry = app(\Noerd\Services\ThemeRegistry::class);

    $theme = \Noerd\Helpers\ThemeHelper::fromLayout(['theme' => $theme ?? null]);
    $themeDefinition = $themeRegistry->get($theme);
    \Noerd\Support\ThemeContext::set($theme);
    $numberedRowIndex = 0;
@endphp
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div @if($theme !== 'default') data-theme="{{ $theme }}" @endif class="grid {{ $themeDefinition->gridClasses }} grid-cols-1 sm:grid-cols-{{$cols ?? '12'}}">
        @foreach($fields ?? [] as $field)
            @if(isset($field['show']) && !$field['show'])
            @elseif(isset($field['viewExists']) && !\Illuminate\Support\Facades\View::exists($field['viewExists']))
            @elseif($field['type'] === 'block')
                {{-- Nested block with its own title and fields --}}
                <div class="{{ $themeDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '12') }}" {!! $getShowIfDirective($field) !!}>
                    @include('noerd::components.detail.block', [
                        'title' => $field['title'] ?? null,
                        'description' => $field['description'] ?? null,
                        'fields' => $field['fields'] ?? [],
                        'cols' => $field['cols'] ?? $cols ?? '12',
                        'modelId' => $modelId ?? null,
                        'theme' => $field['theme'] ?? $theme,
                    ])
                </div>
            @else
                @php
                    $fieldThemeDefinition = $themeRegistry->get($field['theme'] ?? $theme);
                    $fieldTheme = $fieldThemeDefinition->name;
                    $field['theme'] = $fieldTheme;
                    if ($fieldThemeDefinition->numbersRows && ($field['type'] ?? '') !== 'spacer') {
                        $numberedRowIndex++;
                        $field['number'] ??= $numberedRowIndex;
                    }
                @endphp
                <div class="{{ $fieldThemeDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '3') }}" {!! $getShowIfDirective($field) !!}>
                    @php
                        $fieldTypeDefinition = $fieldTypeRegistry->resolve($field['type'] ?? '');
                        $resolvedRendererProps = $fieldTypeDefinition?->resolveProps(
                            $field,
                            $this ?? null,
                            $detailData ?? null,
                            $modelId ?? null,
                        ) ?? [];
                        $resolvedRendererKey = $fieldTypeDefinition?->resolveKey(
                            $field,
                            $this ?? null,
                            $detailData ?? null,
                            $modelId ?? null,
                        );

                        // Element templates live in theme folders; a theme that does not
                        // ship an element falls back to the default theme's template and
                        // finally to the renderer registered on the field type. Livewire
                        // renderers theme via the "-{theme}" suffix convention instead.
                        $rendererTarget = null;
                        if ($fieldTypeDefinition) {
                            $rendererTarget = $fieldTypeDefinition->kind === 'livewire'
                                ? \Noerd\Support\ThemeElementResolver::resolveLivewire($fieldTypeDefinition, $fieldTheme)
                                : \Noerd\Support\ThemeElementResolver::resolveInclude($fieldTypeDefinition, $fieldTheme);
                        }
                    @endphp

                    @if($fieldTypeDefinition?->kind === 'livewire')
                        @livewire(
                            $rendererTarget,
                            $resolvedRendererProps,
                            key($resolvedRendererKey ?? ($field['name'] ?? $field['type']) . '-' . ($modelId ?? 'new'))
                        )
                    @elseif($fieldTypeDefinition?->kind === 'include')
                        @include($rendererTarget, $resolvedRendererProps)
                    @else
                        @php
                            $fieldType = $field['type'] ?? '';
                            $looksLikeRelation = $fieldType === 'relation' || \Illuminate\Support\Str::endsWith($fieldType, 'Relation');
                        @endphp

                        @if($looksLikeRelation)
                            @php
                                throw new \RuntimeException("Relation field type [{$fieldType}] is not registered. Register it in a module service provider and reference that explicit type in YAML.");
                            @endphp
                        @else
                            @include(\Noerd\Support\ThemeElementResolver::resolveFallbackInput($fieldTheme), ['field' => $field])
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
