@include('noerd::components.detail.display-value', [
    'field' => $field,
    'format' => 'select',
    'options' => \Noerd\Helpers\SetupCollectionHelper::selectOptions(
        (string) ($field['collectionKey'] ?? ''),
        (string) ($field['displayField'] ?? 'name'),
        $field['valueField'] ?? null,
    ),
])
