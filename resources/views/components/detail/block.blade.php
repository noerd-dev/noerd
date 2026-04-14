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
@endphp
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div class="grid py-8 pt-4 grid-cols-1 sm:grid-cols-{{$cols ?? '12'}} gap-6">
        @foreach($fields ?? [] as $field)
            @if(isset($field['show']) && !$field['show'])
            @elseif(isset($field['viewExists']) && !\Illuminate\Support\Facades\View::exists($field['viewExists']))
            @elseif($field['type'] === 'block')
                {{-- Nested block with its own title and fields --}}
                <div class="col-span-1 sm:col-span-{{$field['colspan'] ?? '12'}}" {!! $getShowIfDirective($field) !!}>
                    @include('noerd::components.detail.block', [
                        'title' => $field['title'] ?? null,
                        'description' => $field['description'] ?? null,
                        'fields' => $field['fields'] ?? [],
                        'cols' => $field['cols'] ?? $cols ?? '12',
                        'modelId' => $modelId ?? null,
                    ])
                </div>
            @else
                <div class="col-span-1 sm:col-span-{{$field['colspan'] ?? '3'}}" {!! $getShowIfDirective($field) !!}>
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
                    @endphp

                    @if($fieldTypeDefinition?->kind === 'livewire')
                        @livewire(
                            $fieldTypeDefinition->target,
                            $resolvedRendererProps,
                            key($resolvedRendererKey ?? ($field['name'] ?? $field['type']) . '-' . ($modelId ?? 'new'))
                        )
                    @elseif($fieldTypeDefinition?->kind === 'include')
                        @include($fieldTypeDefinition->target, $resolvedRendererProps)
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
                            @include('noerd::components.forms.input', ['field' => $field])
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
