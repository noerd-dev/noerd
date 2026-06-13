@php
    // Self-contained, like list/index.blade.php: pull the config straight from the component.
    $listConfig = $this->with()['listConfig'] ?? [];

    $listId = $listConfig['listId'] ?? '';
    $rows = $listConfig['rows'] ?? [];
    $listSettings = $listConfig['listSettings'] ?? [];
    $allColumns = $listSettings['columns'] ?? [];

    // Restrict to the explicitly requested columns, preserving the declared order.
    // Fall back to all columns when none are specified.
    $minimalColumns = $this->minimalColumns ?? [];
    $table = ! empty($minimalColumns)
        ? collect($minimalColumns)
            ->map(fn ($field) => collect($allColumns)->firstWhere('field', $field))
            ->filter()
            ->values()
            ->all()
        : $allColumns;

    $hasMore = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->total() > $rows->count();

    $formatMinimalValue = function (mixed $value, string $type): string {
        return match ($type) {
            'currency' => is_numeric($value) ? \Noerd\Helpers\CurrencyHelper::format((float) $value) : (string) ($value ?? ''),
            'date' => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y') : '',
            'datetime' => $value ? \Carbon\Carbon::parse($value)->format(app()->getLocale() === 'de' ? 'd.m.Y H:i' : 'Y-m-d H:i') : '',
            'bool', 'boolean' => $value ? __('Yes') : __('No'),
            default => (string) ($value ?? ''),
        };
    };
@endphp

{{-- Break out of the embedding page's p-6 so the table sits flush to the card edges --}}
<div class="-mx-6">
    <table class="min-w-full border-separate border-spacing-0">
        <thead>
        <tr>
            @foreach($table as $column)
                <th scope="col"
                    class="text-{{ $column['align'] ?? 'left' }} border-b border-gray-300 bg-brand-navi/75 py-1 px-2 first:pl-4 last:pr-4 text-[11px] font-medium whitespace-nowrap text-gray-600">
                    {{ __($column['label'] ?? '') }}
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $key => $row)
            <tr wire:key="minimal-{{ $listId }}-{{ $key }}"
                wire:click="findListAction('{{ $key }}')"
                class="cursor-pointer hover:bg-brand-bg">
                @foreach($table as $column)
                    <td class="text-{{ $column['align'] ?? 'left' }} border-b border-gray-100 py-1 px-2 first:pl-4 last:pr-4 text-xs whitespace-nowrap text-gray-700">
                        @php
                            $cellValue = $row[$column['field']] ?? null;
                            $isBadge = ($column['type'] ?? 'text') === 'badge';
                            $badgeValue = $cellValue instanceof \BackedEnum ? $cellValue->value : ($cellValue instanceof \UnitEnum ? $cellValue->name : $cellValue);
                            $badgeLabel = $badgeValue;
                            if ($isBadge) {
                                foreach (($column['options'] ?? []) as $opt) {
                                    if (isset($opt['value']) && (string) $opt['value'] === (string) $badgeValue) {
                                        $badgeLabel = $opt['label'] ?? $badgeValue;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        @if($isBadge)
                            @if($badgeValue !== null && $badgeValue !== '')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-700">
                                    {{ __($badgeLabel) }}
                                </span>
                            @endif
                        @else
                            {{ $formatMinimalValue($cellValue, $column['type'] ?? 'text') }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ max(count($table), 1) }}" class="border-b border-gray-100 px-4 py-4 text-center">
                    <p class="text-xs text-gray-500">{{ __('No entries yet') }}</p>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($hasMore && $this->showMoreComponent)
        <div class="border-t border-gray-200 px-4 py-1.5">
            <button type="button"
                    @click="$modal('{{ $this->showMoreComponent }}', {{ \Illuminate\Support\Js::from($this->showMoreArguments) }})"
                    class="text-xs font-medium text-brand-primary hover:underline">
                {{ __('Show more') }}
            </button>
        </div>
    @endif
</div>
