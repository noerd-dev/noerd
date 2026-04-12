@props(['filter', 'value' => ''])

<select wire:change="storeActiveListFilters"
        wire:model.live="listFilters.{{ $filter['column'] }}"
        class="@if($value !== '') !border-brand-primary !border-solid !border-2 @endif mr-4 min-w-36 max-w-48 truncate rounded-md border border-dashed border-zinc-300 px-3 h-8 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border">
    <option value="">{{ $filter['label'] }}</option>
    @foreach($filter['options'] ?? [] as $key => $option)
        <option value="{{ $key }}">{{ $option }}</option>
    @endforeach
</select>
