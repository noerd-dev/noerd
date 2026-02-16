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
@endphp
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div class="grid py-8 pt-4 grid-cols-1 sm:grid-cols-{{$cols ?? '12'}} gap-6">
        @foreach($fields ?? [] as $field)
            @if(isset($field['show']) && !$field['show'])
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
                    @if($field['type'] === 'relation')
                        @include('noerd::components.forms.input-relation', ['field' => $field, 'modelId' => $modelId ?? null])
                    @elseif($field['type'] === 'collection-select')
                        @include('noerd::components.forms.input-collection-select', ['field' => $field])
                    @elseif($field['type'] === 'select')
                        {{-- options are defined in a yml file --}}
                        @include('noerd::components.forms.input-select', ['field' => $field])
                    @elseif($field['type'] === 'picklist')
                        {{-- options are defined in a computed method --}}
                        @include('noerd::components.forms.picklist', ['field' => $field])
                    @elseif($field['type'] === 'setupCollectionSelect')
                        {{-- options from a setup collection --}}
                        @include('noerd::components.forms.setup-collection-select', ['field' => $field])
                    @elseif($field['type'] === 'belongsToMany')
                        {{-- many-to-many relationship with tag selection UI --}}
                        @include('noerd::components.forms.belongs-to-many', ['field' => $field])
                    @elseif($field['type'] === 'checkbox')
                        @include('noerd::components.forms.checkbox', ['field' => $field])
                    @elseif($field['type'] === 'image')
                        @include('noerd::components.forms.image', ['field' => $field, 'model' => $model ?? null])
                    @elseif($field['type'] === 'richText')
                        @include('noerd::components.forms.rich-text', ['field' => $field])
                    @elseif($field['type'] === 'translatableRichText')
                        @include('noerd::components.forms.translatable-rich-text', ['field' => $field])
                    @elseif($field['type'] === 'translatableText')
                        @include('noerd::components.forms.translatable-text', ['field' => $field])
                    @elseif($field['type'] === 'translatableTextarea')
                        @include('noerd::components.forms.translatable-textarea', ['field' => $field])
                    @elseif($field['type'] === 'button')
                        @include('noerd::components.forms.button', ['field' => $field])
                    @elseif($field['type'] === 'colorHex')
                        @include('noerd::components.forms.color-hex', ['field' => $field])
                    @else
                        @include('noerd::components.forms.input', ['field' => $field])
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
