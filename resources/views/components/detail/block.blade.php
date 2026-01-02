<!-- Framework File -->
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => __($title ?? ''), 'description' => __($description ?? '')])
    @endif
    <div class="grid py-8 pt-4 grid-cols-1 sm:grid-cols-{{$cols ?? '12'}} gap-6">
        @foreach($fields as $field)
            @if(isset($field['show']) && !$field['show'])
            @elseif($field['type'] === 'block')
                {{-- Nested block with its own title and fields --}}
                <div class="col-span-1 sm:col-span-{{$field['colspan'] ?? '12'}}">
                    @include('noerd::components.detail.block', [
                        'title' => $field['title'] ?? null,
                        'description' => $field['description'] ?? null,
                        'fields' => $field['fields'] ?? [],
                        'cols' => $field['cols'] ?? $cols ?? '12',
                        'modelId' => $modelId ?? null,
                    ])
                </div>
            @else
                <div class="col-span-1 sm:col-span-{{$field['colspan'] ?? '3'}}">
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
                    @elseif($field['type'] === 'enum')
                        @php
                            if (isset($field['enumClass']) && !isset($field['options'])) {
                                $field['options'] = $field['enumClass']::cases();
                            }
                        @endphp
                        @include('noerd::components.forms.input-enum', ['field' => $field])
                    @elseif($field['type'] === 'checkbox')
                        @include('noerd::components.forms.checkbox', ['field' => $field])
                    @elseif($field['type'] === 'image')
                        @include('noerd::components.forms.image', ['field' => $field])
                    @elseif($field['type'] === 'richText')
                        @include('noerd::components.forms.rich-text', ['field' => $field])
                    @elseif($field['type'] === 'translatableRichText')
                        @include('noerd::components.forms.translatable-rich-text', ['field' => $field])
                    @elseif($field['type'] === 'translatableText')
                        @include('noerd::components.forms.translatable-text', ['field' => $field])
                    @elseif($field['type'] === 'button')
                        @include('noerd::components.forms.button', ['field' => $field])
                    @else
                        @include('noerd::components.forms.input', ['field' => $field])
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
