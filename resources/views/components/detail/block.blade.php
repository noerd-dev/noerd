<!-- Framework File -->
<div>
    @if(isset($title) || isset($description))
        @include('noerd::components.detail.block-head', ['title' => $title ?? '', 'description' => $description ?? ''])
    @endif
    <div class="grid py-8 pt-4 grid-cols-{{$cols ?? '12'}} gap-6">
        @foreach($fields as $field)
            @if(isset($field['show']) && !$field['show'])
            @else
                <div class="col-span-{{$field['colspan'] ?? '3'}}">
                    @if($field['type'] === 'relation')
                        @include('noerd::components.forms.input-relation', ['field' => $field, 'modelId' => $modelId])
                    @elseif($field['type'] === 'select')
                        {{-- options are defined in a yml file --}}
                        @include('noerd::components.forms.input-select', ['field' => $field])
                    @elseif($field['type'] === 'picklist')
                        {{-- options are defined in a computed method --}}
                        @include('noerd::components.forms.picklist', ['field' => $field])
                    @elseif($field['type'] === 'enum')
                        @include('noerd::components.forms.input-enum', ['field' => $field])
                    @elseif($field['type'] === 'checkbox')
                        @include('noerd::components.forms.checkbox', ['field' => $field])
                    @elseif($field['type'] === 'editor')
                        {{-- TODO Quill Editor --}}
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
