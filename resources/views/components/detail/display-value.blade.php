{{-- One row of a text-only theme (display): the label left, the value as
     plain text right. Every element template of the display theme includes
     this partial; `format` is the template's hint, otherwise the field type
     decides. The value comes from the rendering component, or from
     $detailData when the block is rendered outside a component. --}}
@props([
    'field' => null,
    'format' => null,
    'options' => null,
])

@php
    $field = is_array($field) ? $field : [];
    $displayName = (string) ($field['name'] ?? '');
    $displayValue = data_get(isset($this) ? $this : ['detailData' => $detailData ?? []], $displayName);
    $displayType = (string) ($field['type'] ?? 'text');

    $format ??= match ($displayType) {
        'currency' => 'currency',
        'checkbox' => 'boolean',
        'select', 'picklist', 'setupCollectionSelect' => 'select',
        'textarea' => 'multiline',
        'phone' => 'phone',
        'email' => 'email',
        'date' => 'date',
        'datetime', 'datetime-local' => 'datetime',
        'time' => 'time',
        'number' => 'number',
        default => 'text',
    };

    // Written by the one list-cell formatter, so a value reads the same in a
    // text-only row, a table cell and a CSV export. A select's value/label rows
    // and a picklist's value => label map resolve to the translated label alike.
    $displayText = match ($format) {
        'currency', 'number' => blank($displayValue) ? '' : \Noerd\Support\ListCellFormatter::format($displayValue, ['type' => $format]),
        'boolean' => \Noerd\Support\ListCellFormatter::format($displayValue, ['type' => 'bool']),
        'select' => blank($displayValue)
            ? ''
            : \Noerd\Support\ListCellFormatter::format($displayValue, ['type' => 'badge', 'options' => $options ?? ($field['options'] ?? [])]),
        'date', 'datetime', 'time' => \Noerd\Support\ListCellFormatter::format($displayValue, ['type' => $format]),
        default => \Noerd\Support\ListCellFormatter::text($displayValue),
    };
@endphp

<div class="flex gap-2 text-sm">
    <div class="w-28 shrink-0 truncate text-gray-500" title="{{ __($field['label'] ?? '') }}">{{ __($field['label'] ?? '') }}</div>
    <div @class([
        'min-w-0 text-gray-900',
        'whitespace-pre-line' => $format === 'multiline',
        'truncate' => $format !== 'multiline',
    ])>
        @if ($format === 'phone' && $displayText !== '')
            <a href="tel:{{ $displayText }}" class="hover:underline">{{ $displayText }}</a>
        @elseif ($format === 'email' && $displayText !== '')
            <a href="mailto:{{ $displayText }}" class="hover:underline">{{ $displayText }}</a>
        @else
            {{ $displayText }}
        @endif
    </div>
</div>
