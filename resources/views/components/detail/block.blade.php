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
    $detailViewRegistry = app(\Noerd\Services\DetailViewRegistry::class);

    // Legacy boolean `compact` maps to view 'compact'; an explicit `view` wins.
    $view = $view ?? (($compact ?? false) ? 'compact' : 'default');
    $viewDefinition = $detailViewRegistry->get($view);
    $view = $viewDefinition->name;
    $numberedRowIndex = 0;
@endphp
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div @if($view !== 'default') data-view="{{ $view }}" @endif @if($view === 'compact') data-compact="true" @endif class="grid {{ $viewDefinition->gridClasses }} grid-cols-1 sm:grid-cols-{{$cols ?? '12'}}">
        @foreach($fields ?? [] as $field)
            @if(isset($field['show']) && !$field['show'])
            @elseif(isset($field['viewExists']) && !\Illuminate\Support\Facades\View::exists($field['viewExists']))
            @elseif($field['type'] === 'block')
                {{-- Nested block with its own title and fields --}}
                <div class="{{ $viewDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '12') }}" {!! $getShowIfDirective($field) !!}>
                    @include('noerd::components.detail.block', [
                        'title' => $field['title'] ?? null,
                        'description' => $field['description'] ?? null,
                        'fields' => $field['fields'] ?? [],
                        'cols' => $field['cols'] ?? $cols ?? '12',
                        'modelId' => $modelId ?? null,
                        'view' => $field['view'] ?? (isset($field['compact']) ? ($field['compact'] ? 'compact' : 'default') : $view),
                    ])
                </div>
            @else
                @php
                    $fieldView = $field['view']
                        ?? (isset($field['compact']) ? ($field['compact'] ? 'compact' : 'default') : $view);
                    $fieldViewDefinition = $detailViewRegistry->get($fieldView);
                    $fieldView = $fieldViewDefinition->name;
                    $field['view'] = $fieldView;
                    $field['compact'] = $fieldView === 'compact';
                    if ($fieldViewDefinition->numbersRows) {
                        $numberedRowIndex++;
                        $field['number'] ??= $numberedRowIndex;
                    }
                @endphp
                <div class="{{ $fieldViewDefinition->fullWidthRows ? 'col-span-full' : 'col-span-1 sm:col-span-' . ($field['colspan'] ?? '3') }}" {!! $getShowIfDirective($field) !!}>
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

                        // For non-default views, prefer a parallel variant of the renderer if one exists.
                        // Includes live in forms/{view}/, livewire components use a "-{view}" suffix.
                        // When no variant exists, the original element renders unchanged.
                        $rendererTarget = $fieldTypeDefinition?->target;
                        if ($fieldView !== 'default' && $rendererTarget) {
                            if ($fieldTypeDefinition->kind === 'include') {
                                $variantTarget = \Illuminate\Support\Str::replaceFirst('.forms.', '.forms.' . $fieldView . '.', $rendererTarget);
                                if ($variantTarget !== $rendererTarget && \Illuminate\Support\Facades\View::exists($variantTarget)) {
                                    $rendererTarget = $variantTarget;
                                }
                            } elseif ($fieldTypeDefinition->kind === 'livewire') {
                                $variantNamespace = 'noerd';
                                $variantComponentName = $rendererTarget;
                                if (str_contains($rendererTarget, '::')) {
                                    [$variantNamespace, $variantComponentName] = explode('::', $rendererTarget, 2);
                                }
                                $variantName = $variantComponentName . '-' . $fieldView;
                                if (\Illuminate\Support\Facades\View::exists($variantNamespace . '::components.' . $variantName)) {
                                    $rendererTarget = str_contains($fieldTypeDefinition->target, '::')
                                        ? $variantNamespace . '::' . $variantName
                                        : $variantName;
                                }
                            }
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

                            $fallbackTarget = 'noerd::components.forms.input';
                            if ($fieldView !== 'default' && \Illuminate\Support\Facades\View::exists('noerd::components.forms.' . $fieldView . '.input')) {
                                $fallbackTarget = 'noerd::components.forms.' . $fieldView . '.input';
                            }
                        @endphp

                        @if($looksLikeRelation)
                            @php
                                throw new \RuntimeException("Relation field type [{$fieldType}] is not registered. Register it in a module service provider and reference that explicit type in YAML.");
                            @endphp
                        @else
                            @include($fallbackTarget, ['field' => $field])
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
