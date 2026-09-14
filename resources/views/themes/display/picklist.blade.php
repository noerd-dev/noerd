{{-- The picklist options come from the component's provider method, exactly
     like the control resolves them. --}}
@include('noerd::components.detail.display-value', [
    'field' => $field,
    'format' => 'select',
    'options' => isset($this) && method_exists($this, 'resolvePicklistOptions')
        ? $this->resolvePicklistOptions((string) ($field['picklistField'] ?? ''))
        : [],
])
