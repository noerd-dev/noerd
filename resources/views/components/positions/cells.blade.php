{{-- The configurable cells of a position row: one <x-noerd::positions.cell> per
     resolved column (`$this->positionColumns(...)`), bound to `row.{field}` of a
     component using the NoerdPositionRow trait.

     - editable column → a theme control with `wire:model="row.{field}"` and
       `wire:change="{change}"` (select: an empty option plus the options)
     - readonly scalar column → a disabled control
     - array/JSON value → text, joined by PositionColumn::displayValue()

     The trailing action/trash cell stays in the row component, so it keeps its
     own wire:confirm.

     <x-noerd::positions.cells :theme="$theme" :columns="$columns" /> --}}
@props([
    'theme' => 'default',
    'columns' => [],
    /** Defaults to the `row` property of the rendering row component. */
    'row' => null,
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
    $row ??= $this->row ?? [];
    preg_match('/(?:^|\s)(h-\S+)/', $themeDefinition->controlClasses, $heightMatch);
    $textHeight = $heightMatch[1] ?? 'h-10';
@endphp

@foreach ($columns as $columnData)
    @php
        $column = \Noerd\Support\Positions\PositionColumn::fromArray((array) $columnData);
        $value = $row[$column->field] ?? null;
        $model = 'row.' . $column->field;
    @endphp

    <x-noerd::positions.cell :theme="$theme" :width="$column->width" wire:key="position-cell-{{ $column->field }}">
        @if (\Noerd\Support\Positions\PositionColumn::isArrayValue($value))
            <span class="flex items-center {{ $textHeight }} text-sm text-zinc-700 truncate" title="{{ $column->displayValue($value) }}">{{ $column->displayValue($value) }}</span>
        @elseif ($column->type === 'select')
            <x-noerd::forms.control
                :theme="$theme"
                type="select"
                wire:model="{{ $model }}"
                :wire:change="$column->isEditable() ? $column->change : null"
                :disabled="! $column->isEditable()"
            >
                <option value="">{{ $column->placeholder }}</option>
                @foreach ($column->options as $option)
                    <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                @endforeach
            </x-noerd::forms.control>
        @else
            <x-noerd::forms.control
                :theme="$theme"
                :type="$column->type"
                wire:model="{{ $model }}"
                :wire:change="$column->isEditable() ? $column->change : null"
                :step="$column->type === 'number' ? $column->step : null"
                :disabled="! $column->isEditable()"
            />
        @endif
    </x-noerd::positions.cell>
@endforeach
