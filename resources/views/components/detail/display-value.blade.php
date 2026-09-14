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

    // Option labels: a list of value/label rows (select) or a value => label
    // map (picklist), translated like the control would render them.
    $optionLabel = static function (mixed $value) use ($options, $field): string {
        $rows = $options ?? ($field['options'] ?? []);

        foreach ($rows as $key => $row) {
            $rowValue = is_array($row) ? ($row['value'] ?? null) : $key;
            $rowLabel = is_array($row) ? ($row['label'] ?? $rowValue) : $row;

            if ((string) $rowValue === (string) $value) {
                return __((string) $rowLabel);
            }
        }

        return (string) $value;
    };

    $displayText = match ($format) {
        'currency' => blank($displayValue) ? '' : \Noerd\Helpers\CurrencyHelper::format((float) $displayValue),
        'number' => blank($displayValue) ? '' : \Noerd\Helpers\FormatHelper::number((float) $displayValue),
        'boolean' => $displayValue ? __('Yes') : __('No'),
        'select' => blank($displayValue) ? '' : $optionLabel($displayValue),
        'date' => \Noerd\Helpers\FormatHelper::date($displayValue),
        'datetime' => \Noerd\Helpers\FormatHelper::dateTime($displayValue),
        'time' => \Noerd\Helpers\FormatHelper::time($displayValue),
        default => is_array($displayValue)
            ? implode(', ', array_filter($displayValue, fn($item): bool => is_scalar($item) && $item !== ''))
            : (string) $displayValue,
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
